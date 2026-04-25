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

// Currency from tenant preferences
const currency = window.preferences?.('currency') || 'SDG';
const fmt = (val) => `${Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${currency}`;

// ── Product search ────────────────────────────────────────────────────────────
const search = ref('');
const filteredProducts = computed(() => {
    if (!search.value) return props.products;
    return props.products.filter(p =>
        p.name.toLowerCase().includes(search.value.toLowerCase())
    );
});

// ── Cart ──────────────────────────────────────────────────────────────────────
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

const updateItemQuantity = (item, delta) => {
    item.quantity = Math.max(1, item.quantity + delta);
};

const updateItemPrice = (item, newPrice) => {
    item.price = Math.max(0, parseFloat(newPrice) || 0);
};

const updateItemUnit = (item, unitId) => {
    item.unit_id = unitId || null;
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const cartSubtotal = computed(() =>
    cart.value.reduce((sum, item) => sum + item.price * item.quantity, 0)
);

// ── Discount ──────────────────────────────────────────────────────────────────
const discountValue = ref('');
const discountType = ref('percent'); // 'percent' | 'flat'

const discountAmount = computed(() => {
    const v = parseFloat(discountValue.value) || 0;
    if (v <= 0) return 0;
    if (discountType.value === 'percent') {
        return Math.min(cartSubtotal.value, (cartSubtotal.value * v) / 100);
    }
    return Math.min(cartSubtotal.value, v);
});

const total = computed(() => Math.max(0, cartSubtotal.value - discountAmount.value));

// ── Checkout modal ────────────────────────────────────────────────────────────
const showingCheckoutModal = ref(false);
const selectedPaymentMethod = ref('cash');
const cashTendered = ref('');

const changeAmount = computed(() => {
    if (selectedPaymentMethod.value !== 'cash') return null;
    const tendered = parseFloat(cashTendered.value) || 0;
    return tendered > 0 ? Math.max(0, tendered - total.value) : null;
});

const openCheckoutModal = () => {
    if (cart.value.length === 0) return;
    cashTendered.value = '';
    selectedPaymentMethod.value = 'cash';
    showingCheckoutModal.value = true;
};

const paymentMethods = [
    {
        value: 'cash',
        label: __('Cash'),
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />`,
        color: 'emerald',
    },
    {
        value: 'bank_transfer',
        label: __('Bank Transfer'),
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />`,
        color: 'blue',
    },
    {
        value: 'credit',
        label: __('Credit'),
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />`,
        color: 'orange',
    },
];

const methodColorMap = {
    emerald: {
        active: 'bg-emerald-600 border-emerald-600 text-white',
        inactive: 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-emerald-300',
        icon: 'text-white',
        inactiveIcon: 'text-emerald-500',
    },
    blue: {
        active: 'bg-blue-600 border-blue-600 text-white',
        inactive: 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-blue-300',
        icon: 'text-white',
        inactiveIcon: 'text-blue-500',
    },
    orange: {
        active: 'bg-orange-500 border-orange-500 text-white',
        inactive: 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-orange-300',
        icon: 'text-white',
        inactiveIcon: 'text-orange-500',
    },
};

// ── Checkout form & logic ─────────────────────────────────────────────────────
const cartErrorMessage = ref('');
const showingTransferModal = ref(false);
const showingUnavailableModal = ref(false);
const showingSaleCompleteModal = ref(false);
const transfersRequired = ref([]);
const unavailableProducts = ref([]);
const completedSale = ref({ invoiceId: null, total: 0, paymentMethod: 'cash', change: null });

const localCustomers = ref([...props.customers]);
const showingAddCustomerModal = ref(false);

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

const checkout = () => {
    if (cart.value.length === 0) return;

    cartErrorMessage.value = '';
    checkoutForm.items = cart.value;
    checkoutForm.total = total.value;
    checkoutForm.payment_method = selectedPaymentMethod.value;

    if (!checkoutForm.idempotency_key) {
        checkoutForm.idempotency_key = Date.now().toString();
    }

    router.post(route('pos.checkout'), checkoutForm, {
        onSuccess: (successPage) => {
            const response = successPage.props.flash?.response;

            if (response?.requires_confirmation) {
                showingCheckoutModal.value = false;
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
                paymentMethod: selectedPaymentMethod.value,
                change: changeAmount.value,
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
    checkout();
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

// ── Close session ─────────────────────────────────────────────────────────────
const showingCloseModal = ref(false);

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
    return liveVariance.value >= 0
        ? `+${liveVariance.value.toFixed(2)}`
        : liveVariance.value.toFixed(2);
});

const closeSession = () => closeSessionForm.post(route('pos.close'));
</script>

<template>
    <AppLayout :title="__('POS Session')">
        <div class="flex flex-col lg:flex-row h-[calc(100vh-120px)] gap-4">

            <!-- ── Products Grid ───────────────────────────────────────────── -->
            <div class="flex-grow flex flex-col min-w-0">
                <!-- Search -->
                <div class="mb-4">
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

                <!-- Grid -->
                <div class="flex-grow overflow-y-auto grid grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3 p-1 auto-rows-max">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        type="button"
                        :disabled="product.sale_point_qty === 0 && !product.replenishment"
                        class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 text-start shadow-sm group h-fit flex flex-col transition-all duration-150 active:scale-95"
                        :class="product.sale_point_qty === 0 && !product.replenishment
                            ? 'opacity-40 cursor-not-allowed grayscale'
                            : 'hover:border-emerald-400 hover:shadow-md cursor-pointer'"
                        @click="addToCart(product)"
                    >
                        <!-- Image / icon area -->
                        <div class="w-full aspect-square bg-gray-50 dark:bg-gray-800 rounded-lg mb-3 flex items-center justify-center text-gray-300 group-hover:bg-emerald-50 dark:group-hover:bg-emerald-900/20 group-hover:text-emerald-400 transition-colors overflow-hidden relative">
                            <!-- Transfer badge -->
                            <div v-if="product.sale_point_qty === 0 && product.replenishment" class="absolute top-1.5 start-1.5 flex items-center gap-x-1 px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[9px] font-bold uppercase tracking-wider z-10">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                                {{ __('Transfer') }}
                            </div>
                            <!-- Out of stock overlay -->
                            <div v-else-if="product.sale_point_qty === 0" class="absolute inset-0 bg-gray-900/10 dark:bg-gray-900/30 flex items-center justify-center rounded-lg">
                                <span class="bg-gray-800/80 text-white px-2 py-1 rounded text-[10px] font-bold uppercase">{{ __('Out of Stock') }}</span>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </div>

                        <!-- Name -->
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 truncate leading-snug">{{ product.name }}</h3>

                        <!-- Price + stock -->
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <span class="text-sm font-bold text-emerald-600">{{ fmt(product.price || 0) }}</span>
                            <span v-if="product.sale_point_qty > 0" class="text-[10px] font-medium text-gray-400">{{ product.sale_point_qty }}</span>
                            <span v-else-if="product.replenishment" class="text-[10px] font-medium text-amber-500">{{ __('via warehouse') }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- ── Cart Sidebar ────────────────────────────────────────────── -->
            <div class="w-full lg:w-96 flex flex-col bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">

                <!-- Cart header -->
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                    <div class="flex items-center gap-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ __('Cart') }}</h2>
                        <span v-if="cart.length > 0" class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                            {{ cart.length }}
                        </span>
                    </div>
                    <button @click="showingCloseModal = true" class="text-xs font-semibold text-red-500 hover:text-red-600 transition-colors uppercase tracking-wider">
                        {{ __('Close Session') }}
                    </button>
                </div>

                <!-- Cart items -->
                <div class="flex-grow overflow-y-auto p-4 space-y-2">
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-center text-gray-400 py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mb-4 opacity-20">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <p class="text-sm">{{ __('Cart is empty') }}</p>
                    </div>

                    <div
                        v-for="(item, index) in cart"
                        :key="index"
                        class="flex flex-col p-3 rounded-xl border transition-colors"
                        :class="item.sale_point_qty < item.quantity ? 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800' : 'bg-gray-50 dark:bg-gray-800/50 border-transparent'"
                    >
                        <!-- Row 1: name + remove -->
                        <div class="flex items-start justify-between gap-x-2 mb-2">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ item.name }}</p>
                            <button @click="removeFromCart(index)" class="text-gray-300 hover:text-red-500 transition-colors shrink-0 mt-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Row 2: unit + price -->
                        <div class="flex items-center gap-x-2 mb-2">
                            <select
                                v-if="item.units && item.units.length > 0"
                                :value="item.unit_id"
                                @change="updateItemUnit(item, $event.target.value)"
                                class="text-[10px] py-1 px-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option :value="null">{{ __('Base') }}</option>
                                <option v-for="unit in item.units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                            <div class="flex items-center gap-x-1 flex-1">
                                <span class="text-[10px] text-gray-400 shrink-0">{{ currency }}</span>
                                <input
                                    type="number"
                                    :value="item.price"
                                    @input="updateItemPrice(item, $event.target.value)"
                                    class="w-full text-xs py-1 px-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>

                        <!-- Row 3: qty stepper + line total -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-1">
                                <button @click="updateItemQuantity(item, -1)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-emerald-500 hover:text-white hover:border-emerald-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4" /></svg>
                                </button>
                                <input
                                    type="number"
                                    v-model.number="item.quantity"
                                    class="w-10 text-center text-sm font-bold py-1 px-0 bg-transparent border-0 focus:ring-0"
                                    min="1"
                                />
                                <button @click="updateItemQuantity(item, 1)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-emerald-500 hover:text-white hover:border-emerald-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                                </button>
                            </div>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ fmt(item.quantity * item.price) }}</span>
                        </div>

                        <!-- Transfer / stock warning -->
                        <div v-if="item.sale_point_qty < item.quantity && item.replenishment" class="flex items-center gap-x-1 mt-2 px-2 py-1.5 bg-amber-100 dark:bg-amber-900/20 rounded-lg">
                            <svg class="h-3 w-3 text-amber-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            <span class="text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                                {{ item.replenishment.available_qty }} {{ __('at') }} {{ item.replenishment.warehouse_name }}
                            </span>
                        </div>
                        <div v-else-if="item.sale_point_qty < item.quantity" class="flex items-center gap-x-1 mt-2 px-2 py-1.5 bg-red-50 dark:bg-red-900/10 rounded-lg">
                            <svg class="h-3 w-3 text-red-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span class="text-[10px] font-semibold text-red-600 dark:text-red-400">{{ __('Out of stock everywhere') }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Cart footer ──────────────────────────────────────────── -->
                <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 space-y-3">

                    <!-- Customer -->
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Customer') }}</label>
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

                    <!-- Discount -->
                    <div v-if="cart.length > 0">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5 block">{{ __('Discount') }}</label>
                        <div class="flex items-center gap-x-2">
                            <!-- Type toggle -->
                            <div class="flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shrink-0">
                                <button
                                    type="button"
                                    class="px-2.5 py-1.5 text-xs font-semibold transition-colors"
                                    :class="discountType === 'percent' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400 hover:bg-gray-50'"
                                    @click="discountType = 'percent'"
                                >%</button>
                                <button
                                    type="button"
                                    class="px-2.5 py-1.5 text-xs font-semibold transition-colors"
                                    :class="discountType === 'flat' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400 hover:bg-gray-50'"
                                    @click="discountType = 'flat'"
                                >{{ currency }}</button>
                            </div>
                            <input
                                v-model="discountValue"
                                type="number"
                                min="0"
                                :max="discountType === 'percent' ? 100 : cartSubtotal"
                                :placeholder="discountType === 'percent' ? '0' : '0.00'"
                                class="flex-1 text-sm px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            />
                            <span v-if="discountAmount > 0" class="text-xs font-semibold text-red-500 shrink-0">-{{ fmt(discountAmount) }}</span>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total') }}</span>
                        <div class="text-end">
                            <span v-if="discountAmount > 0" class="block text-xs line-through text-gray-400 dark:text-gray-600 ltr:text-right rtl:text-left">{{ fmt(cartSubtotal) }}</span>
                            <span class="text-2xl font-bold text-emerald-600">{{ fmt(total) }}</span>
                        </div>
                    </div>

                    <!-- Checkout button -->
                    <button
                        type="button"
                        class="w-full py-4 text-base font-semibold rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="cart.some(i => i.sale_point_qty < i.quantity)
                            ? 'bg-amber-500 hover:bg-amber-600 text-white focus:ring-amber-500'
                            : 'bg-emerald-600 hover:bg-emerald-700 text-white focus:ring-emerald-500'"
                        :disabled="cart.length === 0 || checkoutForm.processing || cart.some(i => i.sale_point_qty < i.quantity && !i.replenishment)"
                        @click="openCheckoutModal"
                    >
                        <span v-if="cart.some(i => i.sale_point_qty < i.quantity)">{{ __('Review & Complete') }} — {{ fmt(total) }}</span>
                        <span v-else>{{ __('Complete Sale') }} — {{ fmt(total) }}</span>
                    </button>

                    <!-- Error -->
                    <div v-if="cartErrorMessage" class="p-3 rounded-lg bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-700 text-red-600 dark:text-red-400 text-sm">
                        {{ cartErrorMessage }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Add customer modal ─────────────────────────────────────────── -->
        <QuickAddPartyModal
            :show="showingAddCustomerModal"
            type="customer"
            @close="showingAddCustomerModal = false"
            @created="onCustomerCreated"
        />

        <!-- ── Checkout modal ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showingCheckoutModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center px-4 pb-4 sm:pb-0">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="showingCheckoutModal = false" />
                    <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to-class="opacity-100 translate-y-0 sm:scale-100">
                        <div v-if="showingCheckoutModal" class="relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-sm p-6">

                            <!-- Total -->
                            <div class="text-center mb-6">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ __('Total') }}</p>
                                <p class="text-4xl font-bold text-gray-900 dark:text-white">{{ fmt(total) }}</p>
                                <p v-if="discountAmount > 0" class="text-xs text-emerald-600 mt-1">{{ __('Includes discount of') }} {{ fmt(discountAmount) }}</p>
                            </div>

                            <!-- Payment method selector -->
                            <div class="mb-5">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ __('Payment Method') }}</p>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="method in paymentMethods"
                                        :key="method.value"
                                        type="button"
                                        class="flex flex-col items-center gap-y-2 py-4 px-2 rounded-xl border-2 font-semibold text-sm transition-all duration-150"
                                        :class="selectedPaymentMethod === method.value
                                            ? methodColorMap[method.color].active
                                            : methodColorMap[method.color].inactive"
                                        @click="selectedPaymentMethod = method.value; cashTendered = ''"
                                    >
                                        <svg class="h-6 w-6" :class="selectedPaymentMethod === method.value ? methodColorMap[method.color].icon : methodColorMap[method.color].inactiveIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" v-html="method.icon" />
                                        </svg>
                                        <span class="text-xs leading-tight text-center">{{ method.label }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Cash tendered (only for cash) -->
                            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                                <div v-if="selectedPaymentMethod === 'cash'" class="mb-5 space-y-3">
                                    <div>
                                        <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5 block">{{ __('Amount Tendered') }}</label>
                                        <input
                                            v-model="cashTendered"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :placeholder="fmt(total)"
                                            class="w-full px-4 py-3 text-lg font-semibold text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                        />
                                    </div>
                                    <div
                                        v-if="changeAmount !== null"
                                        class="flex items-center justify-between px-4 py-3 rounded-xl"
                                        :class="changeAmount >= 0 ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'"
                                    >
                                        <span class="text-sm font-semibold" :class="changeAmount >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ __('Change Due') }}</span>
                                        <span class="text-xl font-bold" :class="changeAmount >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ fmt(changeAmount) }}</span>
                                    </div>
                                </div>
                            </Transition>

                            <!-- Actions -->
                            <div class="flex gap-x-3">
                                <button
                                    type="button"
                                    class="flex-1 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    @click="showingCheckoutModal = false"
                                >{{ __('Cancel') }}</button>
                                <button
                                    type="button"
                                    class="flex-1 py-3 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                    :disabled="checkoutForm.processing"
                                    @click="checkout"
                                >
                                    <span v-if="checkoutForm.processing" class="flex items-center justify-center gap-x-2">
                                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        {{ __('Processing...') }}
                                    </span>
                                    <span v-else>{{ __('Confirm Payment') }}</span>
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Sale complete modal ────────────────────────────────────────── -->
        <Modal :show="showingSaleCompleteModal" @close="startNewSale">
            <div class="p-6 text-center">
                <!-- Success icon -->
                <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="w-7 h-7 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>

                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('Sale Completed') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Invoice') }} <span class="font-semibold text-gray-800 dark:text-gray-200">#{{ completedSale.invoiceId }}</span></p>

                <!-- Total + payment method -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 mb-4 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total') }}</span>
                        <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ fmt(completedSale.total) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Payment Method') }}</span>
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ paymentMethods.find(m => m.value === completedSale.paymentMethod)?.label ?? completedSale.paymentMethod }}
                        </span>
                    </div>
                    <div v-if="completedSale.change !== null" class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ __('Change Due') }}</span>
                        <span class="text-lg font-bold text-emerald-700 dark:text-emerald-400">{{ fmt(completedSale.change) }}</span>
                    </div>
                </div>

                <div class="flex gap-x-3">
                    <button
                        @click="printLastReceipt"
                        class="flex-1 inline-flex items-center justify-center gap-x-2 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                        </svg>
                        {{ __('Print Receipt') }}
                    </button>
                    <button
                        @click="startNewSale"
                        class="flex-1 py-2.5 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                    >{{ __('New Sale') }}</button>
                </div>
            </div>
        </Modal>

        <!-- ── Transfer confirmation modal ───────────────────────────────── -->
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

        <!-- ── Unavailable products modal ────────────────────────────────── -->
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

        <!-- ── Close session modal ───────────────────────────────────────── -->
        <Modal :show="showingCloseModal" @close="showingCloseModal = false">
            <div class="p-6">
                <div class="flex items-center gap-x-3 mb-6">
                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600 dark:text-red-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Close POS Session') }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Please verify the cash in hand before closing.') }}</p>
                    </div>
                </div>

                <div v-if="session_stats" class="mb-5 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Opening Float') }}</span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ fmt(session_stats.opening_float) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Cash Sales This Session') }}</span>
                            <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">+ {{ fmt(session_stats.cash_sales_total) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/40">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Expected Closing Float') }}</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ fmt(session_stats.expected_closing_float) }}</span>
                        </div>
                    </div>
                </div>

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
                            :placeholder="session_stats ? String(session_stats.expected_closing_float) : '0.00'"
                        />
                    </div>

                    <div
                        v-if="liveVariance !== null"
                        class="flex items-center justify-between rounded-xl px-4 py-3 transition-colors"
                        :class="liveVariance < 0
                            ? 'bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800'
                            : 'bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800'"
                    >
                        <span class="text-sm font-medium" :class="liveVariance < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'">{{ __('Variance') }}</span>
                        <span class="text-sm font-bold" :class="liveVariance < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'">{{ varianceLabel }}</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-x-3">
                    <button @click="showingCloseModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">{{ __('Cancel') }}</button>
                    <PrimaryButton @click="closeSession" :disabled="closeSessionForm.processing" class="bg-red-600 hover:bg-red-700 focus:ring-red-500">{{ __('Confirm & Close') }}</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
