<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import Pagination from "@/Shared/Pagination.vue";
import EmptySearch from "@/Shared/EmptySearch.vue";
import FilterSidebar from "@/Shared/FilterSidebar.vue";
import DatePicker from "@/Components/DatePicker.vue";
import { watch, ref, computed, onMounted, onUnmounted } from "vue";
import { useQueryString } from "@/Composables/useQueryString";
import { debounce } from "lodash";

const props = defineProps({
    payments: Object,
    summary: Object,
    payment_methods: [Object, Array],
});

const showSidebar = ref(true);
const selectedPayment = ref(null);

const filters = ref({
    search: useQueryString("search").value,
    status: useQueryString("status").value,
    direction: useQueryString("direction").value,
    method: useQueryString("method").value,
    date_from: useQueryString("date_from").value,
    date_to: useQueryString("date_to").value,
    party_type: useQueryString("party_type").value,
    sort_by: useQueryString("sort_by").value || "created_at",
    sort_order: useQueryString("sort_order").value || "desc",
});

const resetFilters = () => {
    filters.value = {
        search: null,
        status: null,
        direction: null,
        method: null,
        date_from: null,
        date_to: null,
        party_type: null,
        sort_by: "created_at",
        sort_order: "desc",
    };
};

const sortByOptions = [
    { label: __("Date"), value: "created_at" },
    { label: __("Payment Number"), value: "id" },
    { label: __("Amount"), value: "amount" },
];

const methodOptions = computed(() => {
    if (Array.isArray(props.payment_methods)) {
        return props.payment_methods.map(m => ({
            value: typeof m === 'object' ? m.value : m,
            label: typeof m === 'object' ? m.label : m,
        }));
    }

    return Object.entries(props.payment_methods || {}).map(([label, value]) => ({
        value: typeof value === 'object' ? value.value : value,
        label,
    }));
});

const methodLabel = (method) => {
    const found = methodOptions.value.find(m => m.value === method);
    return found ? __(found.label) : __(method);
};

function isIncoming(payment) {
    return payment.direction === "in";
}

const formatCurrency = (amount, currency = null) => {
    const validCurrency =
        currency && /^[A-Z]{3}$/.test(currency)
            ? currency
            : preferences("currency") && /^[A-Z]{3}$/.test(preferences("currency"))
            ? preferences("currency")
            : "SDG";

    return new Intl.NumberFormat(window.lang === "ar" ? "ar-SA" : "en-US", {
        style: "currency",
        currency: validCurrency,
    }).format(amount || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return "—";
    return new Intl.DateTimeFormat(window.lang === "ar" ? "ar-SA" : "en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    }).format(new Date(dateStr));
};

function partyType(payment) {
    const type = payment.payable_type || payment.invoice?.invocable_type || "";
    return type.split("\\").pop().toLowerCase();
}

const netAmount = computed(() => {
    return (props.summary?.total_in || 0) - (props.summary?.total_out || 0);
});

function handleEsc(e) {
    if (e.key === "Escape") selectedPayment.value = null;
}

onMounted(() => document.addEventListener("keydown", handleEsc));
onUnmounted(() => document.removeEventListener("keydown", handleEsc));

watch(
    filters,
    debounce(function () {
        router.get(route("payments.index"), filters.value, { preserveState: true });
    }, 300),
    { deep: true }
);
</script>

<template>
    <AppLayout :title="__('Payments')">
        <!-- Page Header -->
        <div class="w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                        {{ __("Payments") }}
                    </h2>
                    <span
                        class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                    >
                        {{ payments.total }} {{ __("Payment") }}
                    </span>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                <button
                    @click="showSidebar = !showSidebar"
                    :class="[
                        'inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700 transition-colors',
                        showSidebar ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20' : '',
                    ]"
                    :title="__('Filters')"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-5 h-5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"
                        />
                    </svg>
                </button>

                <Link
                    :href="route('payments.create')"
                    class="w-full px-5 py-2.5 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                >
                    + {{ __("Record Payment") }}
                </Link>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 mb-6">
            <!-- Total IN -->
            <div
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex items-center gap-x-4"
            >
                <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-600 flex-shrink-0">
                    <svg
                        class="w-5 h-5"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        {{ __("Total IN") }}
                    </p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 tracking-tight">
                        {{ formatCurrency(summary.total_in) }}
                    </p>
                </div>
            </div>

            <!-- Total OUT -->
            <div
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex items-center gap-x-4"
            >
                <div class="p-2 rounded-lg bg-red-500/10 text-red-600 flex-shrink-0">
                    <svg
                        class="w-5 h-5"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        {{ __("Total OUT") }}
                    </p>
                    <p class="text-xl font-bold text-red-600 dark:text-red-400 mt-1 tracking-tight">
                        {{ formatCurrency(summary.total_out) }}
                    </p>
                </div>
            </div>

            <!-- Net -->
            <div
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5 flex items-center gap-x-4"
            >
                <div class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 flex-shrink-0">
                    <svg
                        class="w-5 h-5"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.97Zm-12.75 0L3.505 15.695c-.122.499.106 1.028.589 1.202a5.989 5.989 0 0 0 2.031.352 5.989 5.989 0 0 0 2.031-.352c.483-.174.711-.703.59-1.202L6 4.97Z"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        {{ __("Net") }}
                    </p>
                    <p
                        class="text-xl font-bold mt-1 tracking-tight"
                        :class="netAmount > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : netAmount < 0
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-gray-600 dark:text-gray-400'"
                    >
                        {{ formatCurrency(Math.abs(netAmount)) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row lg:gap-x-6">
            <!-- Sidebar -->
            <FilterSidebar
                v-if="showSidebar"
                v-model:filters="filters"
                :sort-by-options="sortByOptions"
                :all-label="__('All Payments')"
                @reset="resetFilters"
            >
                <template #extra-filters>
                    <!-- Direction Pills -->
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Direction") }}</label>
                        <div class="flex bg-gray-50 border border-gray-200 divide-x divide-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:divide-gray-700 rtl:flex-row-reverse rtl:divide-x-reverse h-9">
                            <button
                                v-for="opt in [
                                    { label: __('All'), value: null },
                                    { label: __('Incoming'), value: 'in' },
                                    { label: __('Outgoing'), value: 'out' },
                                ]"
                                :key="String(opt.value)"
                                @click="filters.direction = opt.value"
                                :class="[
                                    'px-3 flex-1 py-2 text-xs font-semibold whitespace-nowrap transition-colors duration-200 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                                    filters.direction === opt.value || (opt.value === null && !filters.direction)
                                        ? 'bg-gray-100 dark:bg-gray-700'
                                        : 'text-gray-600',
                                ]"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Method") }}</label>
                        <div class="space-y-2">
                            <label
                                v-for="m in methodOptions"
                                :key="m.value"
                                class="flex items-center gap-x-2 cursor-pointer"
                            >
                                <input
                                    type="radio"
                                    :value="m.value"
                                    :checked="filters.method === m.value"
                                    @change="filters.method = filters.method === m.value ? null : m.value"
                                    class="border-gray-300 dark:border-gray-600 text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __(m.label) }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Date Range -->
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Date Range") }}</label>
                        <div class="space-y-2">
                            <DatePicker
                                v-model="filters.date_from"
                                :placeholder="__('From')"
                                class="w-full px-3 py-2 text-xs text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                            />
                            <DatePicker
                                v-model="filters.date_to"
                                :placeholder="__('To')"
                                class="w-full px-3 py-2 text-xs text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                            />
                        </div>
                    </div>

                    <!-- Party Type Pills -->
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Party Type") }}</label>
                        <div class="flex bg-gray-50 border border-gray-200 divide-x divide-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:divide-gray-700 rtl:flex-row-reverse rtl:divide-x-reverse h-9">
                            <button
                                v-for="opt in [
                                    { label: __('All'), value: null },
                                    { label: __('Customer'), value: 'Customer' },
                                    { label: __('Supplier'), value: 'Supplier' },
                                ]"
                                :key="String(opt.value)"
                                @click="filters.party_type = opt.value"
                                :class="[
                                    'px-3 flex-1 py-2 text-xs font-semibold whitespace-nowrap transition-colors duration-200 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                                    filters.party_type === opt.value || (opt.value === null && !filters.party_type)
                                        ? 'bg-gray-100 dark:bg-gray-700'
                                        : 'text-gray-600',
                                ]"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                </template>
            </FilterSidebar>

            <!-- Payments List -->
            <div class="flex-1 min-w-0 overflow-hidden">
                <div
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden transition-all"
                >
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                            <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-6 py-4 whitespace-nowrap text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"
                                    >
                                        #
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-6 py-4 whitespace-nowrap text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"
                                    >
                                        {{ __("Party") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-6 py-4 whitespace-nowrap text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"
                                    >
                                        {{ __("Amount") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-6 py-4 whitespace-nowrap text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"
                                    >
                                        {{ __("Method") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-6 py-4 whitespace-nowrap text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"
                                    >
                                        {{ __("Treasury") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-6 py-4 whitespace-nowrap text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"
                                    >
                                        {{ __("Reference") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-6 py-4 whitespace-nowrap text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"
                                    >
                                        {{ __("Invoice Balance") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-6 py-4 whitespace-nowrap text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"
                                    >
                                        {{ __("Date") }}
                                    </th>

                                    <th scope="col" class="relative py-4 px-6">
                                        <span class="sr-only">{{ __("View") }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white dark:bg-gray-900 divide-y divide-gray-200/60 dark:divide-gray-700/60"
                            >
                                <tr
                                    v-for="payment in payments.data"
                                    :key="payment.id"
                                    class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer"
                                    @click="selectedPayment = payment"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ payment.id }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex flex-col">
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{
                                                    payment.invoice?.invocable?.name ||
                                                    payment.payable?.name ||
                                                    "—"
                                                }}
                                            </span>
                                            <span
                                                class="mt-0.5 inline-flex items-center px-1.5 py-0.5 text-[9px] font-medium rounded-md self-start"
                                                :class="
                                                    partyType(payment) === 'customer'
                                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                                        : 'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400'
                                                "
                                            >
                                                {{ __(partyType(payment)) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                        <div class="flex items-center gap-x-1.5">
                                            <span
                                                class="inline-flex items-center justify-center w-5 h-5 rounded-full shrink-0"
                                                :class="
                                                    isIncoming(payment)
                                                        ? 'bg-emerald-100 dark:bg-emerald-900/30'
                                                        : 'bg-red-100 dark:bg-red-900/30'
                                                "
                                            >
                                                <svg
                                                    v-if="isIncoming(payment)"
                                                    class="w-3 h-3 text-emerald-600 dark:text-emerald-400"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2.5"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3"
                                                    />
                                                </svg>
                                                <svg
                                                    v-else
                                                    class="w-3 h-3 text-red-600 dark:text-red-400"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2.5"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18"
                                                    />
                                                </svg>
                                            </span>
                                            <span
                                                :class="
                                                    isIncoming(payment)
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-red-600 dark:text-red-400'
                                                "
                                            >
                                                {{ formatCurrency(payment.amount, payment.currency) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="{
                                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400':
                                                    payment.payment_method === 'cash',
                                                'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400':
                                                    payment.payment_method === 'bank_transfer',
                                                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400':
                                                    payment.payment_method === 'cheque',
                                                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400':
                                                    payment.payment_method === 'credit',
                                            }"
                                        >
                                            {{ methodLabel(payment.payment_method) }}
                                        </span>
                                    </td>

                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ payment.treasury_account?.name ?? "—" }}
                                    </td>

                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        <span class="truncate max-w-[120px] block">
                                            {{ payment.reference ?? "—" }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <template v-if="payment.invoice">
                                            <span
                                                :class="payment.invoice.remaining_balance > 0
                                                    ? 'text-red-600 dark:text-red-400 font-medium'
                                                    : 'text-gray-600 dark:text-gray-400'"
                                            >
                                                {{ formatCurrency(payment.invoice.remaining_balance) }}
                                            </span>
                                        </template>
                                        <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                    </td>

                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ formatDate(payment.paid_at) }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-end">
                                        <button
                                            @click.stop="selectedPayment = payment"
                                            class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:text-gray-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20 rounded-lg transition-all"
                                            :title="__('Details')"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                class="w-4 h-4 rtl:rotate-180"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="m8.25 4.5 7.5 7.5-7.5 7.5"
                                                />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <EmptySearch :data="payments.data" />

                <div class="flex justify-center mt-8">
                    <Pagination :links="payments.links" />
                </div>
            </div>
        </div>

        <!-- Slide-over Backdrop -->
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="selectedPayment"
                class="fixed inset-0 z-40 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm"
                @click="selectedPayment = null"
            />
        </Transition>

        <!-- Slide-over Panel -->
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="ltr:translate-x-full rtl:-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="ease-in duration-200"
            leave-from-class="translate-x-0"
            leave-to-class="ltr:translate-x-full rtl:-translate-x-full"
        >
            <div
                v-if="selectedPayment"
                class="fixed inset-y-0 end-0 z-50 w-full max-w-md bg-white dark:bg-gray-900 shadow-xl border-s border-gray-200 dark:border-gray-700 overflow-y-auto"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                >
                    <div class="flex items-center gap-x-2">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            #{{ selectedPayment.id }}
                        </span>
                        <span
                            :class="
                                selectedPayment.direction === 'in'
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'
                                    : 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800'
                            "
                            class="inline-flex items-center px-2 py-0.5 text-[11px] font-bold rounded-lg border"
                        >
                            {{ selectedPayment.direction === "in" ? __("Incoming") : __("Outgoing") }}
                        </span>
                        <span
                            class="inline-flex items-center px-2 py-0.5 text-[11px] font-bold rounded-lg border"
                            :class="{
                                'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800':
                                    selectedPayment.payment_method === 'cash',
                                'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800':
                                    selectedPayment.payment_method === 'bank_transfer',
                                'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400 dark:border-yellow-800':
                                    selectedPayment.payment_method === 'cheque',
                                'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700':
                                    selectedPayment.payment_method === 'credit',
                            }"
                        >
                            {{ methodLabel(selectedPayment.payment_method) }}
                        </span>
                    </div>
                    <button
                        @click="selectedPayment = null"
                        class="p-1.5 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    >
                        <svg
                            class="h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Amount Hero -->
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                        {{ __("Amount") }}
                    </p>
                    <p
                        class="text-2xl font-bold"
                        :class="
                            selectedPayment.direction === 'in'
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-red-600 dark:text-red-400'
                        "
                    >
                        {{ formatCurrency(selectedPayment.amount, selectedPayment.currency) }}
                    </p>
                </div>

                <!-- Detail Rows -->
                <div class="px-6 py-4 space-y-4">
                    <!-- Party -->
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                            {{ __("Party") }}
                        </p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{
                                selectedPayment.invoice?.invocable?.name ||
                                selectedPayment.payable?.name ||
                                "—"
                            }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                            {{ __(partyType(selectedPayment)) }}
                        </p>
                    </div>

                    <!-- Invoice -->
                    <div v-if="selectedPayment.invoice">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                            {{ __("Invoice") }}
                        </p>
                        <Link
                            :href="route('invoices.show', selectedPayment.invoice.id)"
                            class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline font-medium"
                        >
                            #{{
                                selectedPayment.invoice.serial_number ||
                                selectedPayment.invoice.id
                            }}
                        </Link>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ __("Remaining") }}:
                            {{ formatCurrency(selectedPayment.invoice.remaining_balance) }}
                        </p>
                    </div>
                    <div v-else>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                            {{ __("Invoice") }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                            {{ __("Standalone payment") }}
                        </p>
                    </div>

                    <!-- Treasury Account -->
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                            {{ __("Treasury Account") }}
                        </p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            {{ selectedPayment.treasury_account?.name ?? "—" }}
                        </p>
                    </div>

                    <!-- Reference -->
                    <div v-if="selectedPayment.reference">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                            {{ __("Reference") }}
                        </p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            {{ selectedPayment.reference }}
                        </p>
                    </div>

                    <!-- Notes -->
                    <div v-if="selectedPayment.notes">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                            {{ __("Notes") }}
                        </p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                            {{ selectedPayment.notes }}
                        </p>
                    </div>

                    <!-- Receipt -->
                    <div v-if="selectedPayment.receipt_path">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                            {{ __("Receipt") }}
                        </p>
                        <a
                            :href="'/storage/' + selectedPayment.receipt_path"
                            target="_blank"
                            class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline"
                        >
                            {{ __("View Receipt") }}
                        </a>
                    </div>

                    <!-- Recorded By / Date -->
                    <div
                        class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100 dark:border-gray-800"
                    >
                        <div>
                            <p
                                class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1"
                            >
                                {{ __("Recorded By") }}
                            </p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{ selectedPayment.created_by?.name ?? "—" }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1"
                            >
                                {{ __("Date") }}
                            </p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{ formatDate(selectedPayment.paid_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>
