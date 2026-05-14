<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import QRCode from "qrcode";

const props = defineProps({
    invoice: Object,
    qr_url: String,
    logo: { type: String, default: null },
    headline: { type: String, default: null },
    currency: { type: String, default: "SDG" },
});

const dir = computed(() => (usePage().props.locale === "ar" ? "rtl" : "ltr"));

const qrSvg = ref("");

onMounted(async () => {
    if (props.qr_url) {
        qrSvg.value = await QRCode.toString(props.qr_url, {
            type: "svg",
            margin: 0,
            width: 80,
        });
    }
    window.print();
});

const fmt = (n) => Number(n || 0).toFixed(2);

const deliveredTransactions = computed(() =>
    (props.invoice.transactions || []).filter((t) => t.delivered),
);
const pendingTransactions = computed(() =>
    (props.invoice.transactions || []).filter((t) => !t.delivered),
);

const subtotal = computed(() => Number(props.invoice.subtotal || 0));
const discount = computed(() => Number(props.invoice.discount || 0));
const total = computed(() => Number(props.invoice.total || 0));
const paid = computed(() => Number(props.invoice.paid_amount || 0));
const balance = computed(() => Number(props.invoice.remaining_balance || 0));

const paymentStatus = computed(() => {
    const v = props.invoice.payment_status;
    return typeof v === "object" ? v?.value : v;
});

const isPaid = computed(() => paymentStatus.value === "paid");
const isUnpaid = computed(() => paymentStatus.value === "unpaid");

const statusLabel = computed(() => {
    const map = {
        paid: __("Paid"),
        partially_paid: __("Partially paid"),
        unpaid: __("Unpaid"),
        overdue: __("Overdue"),
    };
    return map[paymentStatus.value] ?? paymentStatus.value;
});

const paymentMethodLabel = computed(() => {
    const v = props.invoice.payment_method;
    return typeof v === "object" ? v?.value : (v ?? "—");
});
</script>

<template>
    <div :dir="dir" class="min-h-screen bg-white p-8 print:p-0 text-gray-900">
        <div class="max-w-3xl mx-auto relative">
            <!-- Watermark -->
            <div
                v-if="isPaid"
                class="pointer-events-none absolute inset-0 flex items-center justify-center"
            >
                <span class="text-[120px] font-black text-emerald-600/15 -rotate-12 select-none">
                    {{ __("Paid") }}
                </span>
            </div>
            <div
                v-else-if="isUnpaid"
                class="pointer-events-none absolute inset-0 flex items-center justify-center"
            >
                <span class="text-[120px] font-black text-red-600/15 -rotate-12 select-none">
                    {{ __("Unpaid") }}
                </span>
            </div>

            <!-- Header -->
            <div class="flex items-start justify-between mb-8 border-b border-gray-200 pb-6">
                <div class="flex items-start gap-4">
                    <img
                        v-if="logo"
                        :src="logo"
                        alt="Logo"
                        class="h-12 w-auto object-contain"
                    />
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ __("Invoice") }}</h1>
                        <p
                            v-if="headline"
                            class="mt-1 text-xs text-gray-500 whitespace-pre-line"
                        >
                            {{ headline }}
                        </p>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Status") }}
                    </div>
                    <span
                        class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full"
                        :class="{
                            'bg-emerald-50 text-emerald-700 border border-emerald-200': isPaid,
                            'bg-red-50 text-red-700 border border-red-200': isUnpaid,
                            'bg-amber-50 text-amber-700 border border-amber-200':
                                !isPaid && !isUnpaid,
                        }"
                    >
                        {{ statusLabel }}
                    </span>
                </div>
            </div>

            <!-- Meta strip -->
            <div class="grid grid-cols-3 gap-6 mb-8 text-sm">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Invoice #") }}
                    </p>
                    <p class="font-semibold text-gray-900">
                        {{ invoice.serial_number ?? `#${invoice.id}` }}
                    </p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Date") }}
                    </p>
                    <p class="text-gray-900">{{ invoice.created_at?.slice(0, 10) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Payment Method") }}
                    </p>
                    <p class="text-gray-900">{{ __(paymentMethodLabel) }}</p>
                </div>
            </div>

            <!-- Bill To -->
            <div v-if="invoice.invocable" class="mb-8">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                    {{ __("Bill To") }}
                </p>
                <p class="text-sm font-semibold text-gray-900">{{ invoice.invocable.name }}</p>
                <p
                    v-if="invoice.invocable.phone_number"
                    class="text-sm text-gray-600"
                >
                    {{ invoice.invocable.phone_number }}
                </p>
                <p v-if="invoice.invocable.address" class="text-sm text-gray-600">
                    {{ invoice.invocable.address }}
                </p>
            </div>

            <!-- Items table -->
            <table class="w-full mb-8 border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500 w-8">
                            #
                        </th>
                        <th class="px-3 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                            {{ __("Product") }}
                        </th>
                        <th class="px-3 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                            {{ __("Qty") }}
                        </th>
                        <th class="px-3 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                            {{ __("Unit") }}
                        </th>
                        <th class="px-3 py-3 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                            {{ __("Unit Price") }}
                        </th>
                        <th class="px-3 py-3 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                            {{ __("Total") }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="(record, idx) in deliveredTransactions" :key="record.id">
                        <td class="px-3 py-2.5 text-xs text-gray-400">{{ idx + 1 }}</td>
                        <td class="px-3 py-2.5 text-sm">
                            <p class="font-semibold text-gray-900">{{ record.product?.name }}</p>
                            <p
                                v-if="record.description"
                                class="text-xs text-gray-500"
                            >
                                {{ record.description }}
                            </p>
                        </td>
                        <td class="px-3 py-2.5 text-sm text-gray-900 whitespace-nowrap">
                            {{ record.quantity }}
                        </td>
                        <td class="px-3 py-2.5 text-sm text-gray-500">
                            {{ record.unit?.name ?? __("Unit") }}
                        </td>
                        <td class="px-3 py-2.5 text-sm text-gray-900 text-end whitespace-nowrap">
                            {{ fmt(record.price) }} {{ currency }}
                        </td>
                        <td class="px-3 py-2.5 text-sm font-semibold text-emerald-700 text-end whitespace-nowrap">
                            {{ fmt(record.price * record.quantity) }} {{ currency }}
                        </td>
                    </tr>

                    <template v-if="pendingTransactions.length">
                        <tr>
                            <td
                                colspan="6"
                                class="px-3 py-2 text-center text-xs font-semibold text-gray-500 bg-gray-100 border-t-2 border-gray-200"
                            >
                                ── {{ __("Pending Delivery") }} ──
                            </td>
                        </tr>
                        <tr
                            v-for="(record, idx) in pendingTransactions"
                            :key="`pending-${record.id}`"
                            class="opacity-65"
                        >
                            <td class="px-3 py-2.5 text-xs text-gray-400">{{ idx + 1 }}</td>
                            <td class="px-3 py-2.5 text-sm text-gray-900">
                                {{ record.product?.name }}
                            </td>
                            <td class="px-3 py-2.5 text-sm text-gray-900 whitespace-nowrap">
                                {{ record.quantity }}
                            </td>
                            <td class="px-3 py-2.5 text-sm text-gray-500">
                                {{ record.unit?.name ?? __("Unit") }}
                            </td>
                            <td class="px-3 py-2.5 text-sm text-gray-900 text-end whitespace-nowrap">
                                {{ fmt(record.price) }} {{ currency }}
                            </td>
                            <td class="px-3 py-2.5 text-sm text-gray-900 text-end whitespace-nowrap">
                                {{ fmt(record.price * record.quantity) }} {{ currency }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <!-- Bottom row: payment history + summary -->
            <div class="flex flex-wrap gap-6 items-end mb-8">
                <div v-if="invoice.payments?.length" class="flex-1 min-w-[260px]">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                        {{ __("Payment History") }}
                    </p>
                    <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                                    {{ __("Date") }}
                                </th>
                                <th class="px-3 py-2 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                                    {{ __("Method") }}
                                </th>
                                <th class="px-3 py-2 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                                    {{ __("Amount") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="payment in invoice.payments" :key="payment.id">
                                <td class="px-3 py-2 text-sm text-gray-500 whitespace-nowrap">
                                    {{ payment.paid_at?.slice(0, 10) }}
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-700">
                                    {{
                                        __(
                                            (typeof payment.payment_method === "object"
                                                ? payment.payment_method?.value
                                                : payment.payment_method) ?? "—",
                                        )
                                    }}
                                </td>
                                <td class="px-3 py-2 text-sm font-semibold text-emerald-700 text-end whitespace-nowrap">
                                    {{ fmt(payment.amount) }} {{ currency }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="ms-auto w-72">
                    <div class="border border-gray-200 rounded-lg p-4 space-y-2">
                        <div class="flex justify-between text-sm text-gray-700">
                            <span>{{ __("Subtotal") }}</span>
                            <span class="whitespace-nowrap">{{ fmt(subtotal) }} {{ currency }}</span>
                        </div>
                        <div
                            v-if="discount > 0"
                            class="flex justify-between text-sm text-red-600"
                        >
                            <span>{{ __("Discount") }}</span>
                            <span class="whitespace-nowrap">− {{ fmt(discount) }} {{ currency }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-gray-900 border-t border-gray-200 pt-2">
                            <span>{{ __("Total") }}</span>
                            <span class="whitespace-nowrap">{{ fmt(total) }} {{ currency }}</span>
                        </div>
                        <div v-if="paid > 0" class="flex justify-between text-sm text-gray-700">
                            <span>{{ __("Paid") }}</span>
                            <span class="whitespace-nowrap">{{ fmt(paid) }} {{ currency }}</span>
                        </div>
                        <div
                            v-if="balance > 0"
                            class="flex justify-between text-sm font-bold text-red-600 border-t border-gray-200 pt-2"
                        >
                            <span>{{ __("Balance Due") }}</span>
                            <span class="whitespace-nowrap">{{ fmt(balance) }} {{ currency }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-end justify-between border-t border-gray-200 pt-4">
                <p class="text-xs text-gray-500">{{ __("Thank you for your business") }}</p>
                <div v-if="qrSvg" v-html="qrSvg" class="w-20 h-20"></div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4;
        margin: 12mm;
    }
}
</style>
