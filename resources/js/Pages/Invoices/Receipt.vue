<script setup>
import { computed, onMounted } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    invoice: Object,
    logo: { type: String, default: null },
    headline: { type: String, default: null },
    currency: { type: String, default: "SDG" },
});

const dir = computed(() => (usePage().props.locale === "ar" ? "rtl" : "ltr"));

onMounted(() => window.print());

const fmt = (n) => Number(n || 0).toFixed(2);

const deliveredTransactions = computed(() =>
    (props.invoice.transactions || []).filter((t) => t.delivered),
);

const subtotal = computed(() => Number(props.invoice.subtotal || 0));
const discount = computed(() => Number(props.invoice.discount || 0));
const total = computed(() => Number(props.invoice.total || 0));
const paid = computed(() => Number(props.invoice.paid_amount || 0));
const balance = computed(() => Number(props.invoice.remaining_balance || 0));
</script>

<template>
    <div :dir="dir" class="receipt mx-auto bg-white text-gray-900 p-3 print:p-2">
        <!-- Header -->
        <div class="text-center border-b border-dashed border-gray-400 pb-2 mb-2">
            <img
                v-if="logo"
                :src="logo"
                alt="Logo"
                class="h-10 mx-auto mb-1 object-contain"
            />
            <p v-if="headline" class="text-[11px] whitespace-pre-line">
                {{ headline }}
            </p>
        </div>

        <!-- Meta -->
        <div class="text-[11px] mb-2 leading-tight">
            <div class="flex justify-between">
                <span>{{ __("Invoice #") }}</span>
                <span class="font-semibold">{{ invoice.serial_number ?? `#${invoice.id}` }}</span>
            </div>
            <div class="flex justify-between">
                <span>{{ __("Date") }}</span>
                <span>{{ invoice.created_at?.slice(0, 16).replace("T", " ") }}</span>
            </div>
            <div v-if="invoice.invocable" class="flex justify-between">
                <span>{{ __("Customer") }}</span>
                <span class="font-semibold">{{ invoice.invocable.name }}</span>
            </div>
        </div>

        <!-- Items -->
        <table class="w-full text-[11px] border-t border-dashed border-gray-400 pt-2 mb-2">
            <thead>
                <tr class="border-b border-dashed border-gray-400">
                    <th class="text-start font-semibold pb-1">{{ __("Item") }}</th>
                    <th class="text-end font-semibold pb-1">{{ __("Qty") }}</th>
                    <th class="text-end font-semibold pb-1">{{ __("Total") }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="record in deliveredTransactions"
                    :key="record.id"
                    class="align-top"
                >
                    <td class="py-0.5">
                        {{ record.product?.name }}
                        <span v-if="record.price" class="block text-[10px] text-gray-500">
                            {{ fmt(record.price) }} × {{ record.quantity }}
                        </span>
                    </td>
                    <td class="py-0.5 text-end">{{ record.quantity }}</td>
                    <td class="py-0.5 text-end font-semibold whitespace-nowrap">
                        {{ fmt(record.price * record.quantity) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="text-[11px] border-t border-dashed border-gray-400 pt-2 space-y-0.5">
            <div class="flex justify-between">
                <span>{{ __("Subtotal") }}</span>
                <span>{{ fmt(subtotal) }} {{ currency }}</span>
            </div>
            <div v-if="discount > 0" class="flex justify-between">
                <span>{{ __("Discount") }}</span>
                <span>− {{ fmt(discount) }} {{ currency }}</span>
            </div>
            <div class="flex justify-between text-[13px] font-bold border-t border-gray-400 pt-1 mt-1">
                <span>{{ __("Total") }}</span>
                <span>{{ fmt(total) }} {{ currency }}</span>
            </div>
            <div v-if="paid > 0" class="flex justify-between">
                <span>{{ __("Paid") }}</span>
                <span>{{ fmt(paid) }} {{ currency }}</span>
            </div>
            <div v-if="balance > 0" class="flex justify-between font-bold">
                <span>{{ __("Balance") }}</span>
                <span>{{ fmt(balance) }} {{ currency }}</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-[10px] mt-3 border-t border-dashed border-gray-400 pt-2">
            {{ __("Thank you for your business") }}
        </div>
    </div>
</template>

<style>
.receipt {
    width: 80mm;
    max-width: 80mm;
}

@media print {
    @page {
        size: 80mm auto;
        margin: 0;
    }
    body {
        margin: 0;
    }
    .receipt {
        width: 80mm;
        max-width: 80mm;
    }
}
</style>
