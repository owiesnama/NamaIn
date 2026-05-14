<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import QRCode from "qrcode";

const props = defineProps({
    customer: Object,
    start_date: String,
    end_date: String,
    opening_balance: { type: [Number, String], default: 0 },
    total_debits: { type: [Number, String], default: 0 },
    total_credits: { type: [Number, String], default: 0 },
    closing_balance: { type: [Number, String], default: 0 },
    activities: { type: Array, default: () => [] },
    qr_url: String,
    logo: { type: String, default: null },
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
const today = new Date().toISOString().slice(0, 10);

const openingBalance = computed(() => Number(props.opening_balance || 0));
const closingBalance = computed(() => Number(props.closing_balance || 0));
const totalDebits = computed(() => Number(props.total_debits || 0));
const totalCredits = computed(() => Number(props.total_credits || 0));
</script>

<template>
    <div :dir="dir" class="min-h-screen bg-white p-8 print:p-0 text-gray-900">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6 border-b border-gray-200 pb-4">
                <div class="flex items-center gap-3">
                    <img
                        v-if="logo"
                        :src="logo"
                        alt="Logo"
                        class="h-12 w-auto object-contain"
                    />
                    <h1 class="text-2xl font-bold text-gray-900">
                        {{ __("Account Statement") }}
                    </h1>
                </div>
                <div class="text-end text-xs text-gray-500">
                    {{ __("Generated") }}: {{ today }}
                </div>
            </div>

            <!-- Meta strip -->
            <div class="grid grid-cols-3 gap-6 mb-6 text-sm">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Account") }}
                    </p>
                    <p class="font-semibold text-gray-900">{{ customer.name }}</p>
                    <p
                        v-if="customer.phone_number"
                        class="text-xs text-gray-600"
                    >
                        {{ customer.phone_number }}
                    </p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Period") }}
                    </p>
                    <p class="text-gray-900">{{ start_date }} — {{ end_date }}</p>
                </div>
                <div v-if="customer.address">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Address") }}
                    </p>
                    <p class="text-xs text-gray-600">{{ customer.address }}</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-4 gap-3 mb-6">
                <div class="border border-gray-200 rounded-lg p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Opening Balance") }}
                    </p>
                    <p class="text-lg font-bold text-gray-900">{{ fmt(openingBalance) }}</p>
                    <p class="text-[10px] text-gray-400">{{ currency }}</p>
                </div>
                <div class="border border-red-200 rounded-lg p-3 bg-red-50">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-red-500 mb-1">
                        {{ __("Total Invoiced") }}
                    </p>
                    <p class="text-lg font-bold text-red-700">{{ fmt(totalDebits) }}</p>
                    <p class="text-[10px] text-red-500">{{ currency }}</p>
                </div>
                <div class="border border-emerald-200 rounded-lg p-3 bg-emerald-50">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 mb-1">
                        {{ __("Total Paid") }}
                    </p>
                    <p class="text-lg font-bold text-emerald-700">{{ fmt(totalCredits) }}</p>
                    <p class="text-[10px] text-emerald-600">{{ currency }}</p>
                </div>
                <div
                    class="border rounded-lg p-3"
                    :class="
                        closingBalance > 0
                            ? 'border-red-200 bg-red-50'
                            : 'border-emerald-200 bg-emerald-50'
                    "
                >
                    <p
                        class="text-[10px] font-bold uppercase tracking-wider mb-1"
                        :class="closingBalance > 0 ? 'text-red-500' : 'text-emerald-600'"
                    >
                        {{ __("Closing Balance") }}
                    </p>
                    <p
                        class="text-lg font-bold"
                        :class="closingBalance > 0 ? 'text-red-700' : 'text-emerald-700'"
                    >
                        {{ fmt(Math.abs(closingBalance)) }}
                    </p>
                    <p
                        class="text-[10px]"
                        :class="closingBalance > 0 ? 'text-red-500' : 'text-emerald-600'"
                    >
                        {{ currency }}
                        <span v-if="closingBalance < 0">({{ __("credit") }})</span>
                    </p>
                </div>
            </div>

            <!-- Ledger -->
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                {{ __("Transaction History") }}
            </p>
            <table class="w-full mb-6 border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500 w-24">
                            {{ __("Date") }}
                        </th>
                        <th class="px-3 py-2 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                            {{ __("Description") }}
                        </th>
                        <th class="px-3 py-2 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500 w-28">
                            {{ __("Debit") }}
                        </th>
                        <th class="px-3 py-2 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500 w-28">
                            {{ __("Credit") }}
                        </th>
                        <th class="px-3 py-2 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500 w-32">
                            {{ __("Balance") }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr class="bg-gray-50">
                        <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">
                            {{ start_date }}
                        </td>
                        <td class="px-3 py-2 text-xs font-semibold text-gray-700">
                            {{ __("Opening Balance") }}
                        </td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2 text-end text-xs font-semibold whitespace-nowrap">
                            {{ fmt(openingBalance) }} {{ currency }}
                        </td>
                    </tr>
                    <tr v-for="(activity, idx) in activities" :key="idx">
                        <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">
                            {{ String(activity.date).slice(0, 10) }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-700">
                            {{ activity.description }}
                        </td>
                        <td class="px-3 py-2 text-xs text-end text-red-600 whitespace-nowrap">
                            <span v-if="Number(activity.debit) > 0">
                                {{ fmt(activity.debit) }} {{ currency }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs text-end text-emerald-700 whitespace-nowrap">
                            <span v-if="Number(activity.credit) > 0">
                                {{ fmt(activity.credit) }} {{ currency }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs text-end font-semibold whitespace-nowrap">
                            {{ fmt(activity.running_balance) }} {{ currency }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="2" class="px-3 py-2 text-xs text-end font-semibold">
                            {{ __("Totals") }}
                        </td>
                        <td class="px-3 py-2 text-xs text-end text-red-600 font-semibold whitespace-nowrap">
                            {{ fmt(totalDebits) }} {{ currency }}
                        </td>
                        <td class="px-3 py-2 text-xs text-end text-emerald-700 font-semibold whitespace-nowrap">
                            {{ fmt(totalCredits) }} {{ currency }}
                        </td>
                        <td class="px-3 py-2 text-xs text-end font-bold whitespace-nowrap">
                            {{ fmt(closingBalance) }} {{ currency }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- Footer -->
            <div class="flex items-end justify-between border-t border-gray-200 pt-4">
                <div>
                    <div class="border-t border-gray-400 w-48 pt-1 text-xs text-gray-600">
                        {{ __("Authorized Signature") }}
                    </div>
                </div>
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
