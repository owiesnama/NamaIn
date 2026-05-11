<script setup>
import { computed, onMounted } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    quote: Object,
});

const dir = computed(() => usePage().props.locale === 'ar' ? 'rtl' : 'ltr');

const subtotal = computed(() =>
    props.quote.items.reduce((sum, item) => sum + item.quantity * parseFloat(item.unit_price), 0)
);

const total = computed(() => Math.max(0, subtotal.value - parseFloat(props.quote.discount || 0)));

onMounted(() => window.print());

const statusLabel = (status) => {
    const v = status?.value ?? status;
    const map = { active: __("Active"), converted: __("Converted"), expired: __("Expired") };
    return map[v] ?? v;
};
</script>

<template>
    <div :dir="dir" class="min-h-screen bg-white p-8 print:p-0">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="flex items-start justify-between mb-8 border-b border-gray-200 pb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __("Price Quotation") }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ __("Quote number") }}: {{ quote.number }}</p>
                    <p v-if="quote.expires_at" class="text-sm text-gray-500">
                        {{ __("Expiry date") }}: {{ quote.expires_at }}
                    </p>
                </div>
                <div class="text-end">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">{{ __("Status") }}</div>
                    <span class="text-sm font-semibold text-gray-700">{{ statusLabel(quote.status) }}</span>
                </div>
            </div>

            <!-- Customer info -->
            <div v-if="quote.customer" class="mb-8">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">{{ __("Customer") }}</p>
                <p class="text-sm font-semibold text-gray-900">{{ quote.customer.name }}</p>
                <p v-if="quote.customer.phone_number" class="text-sm text-gray-600">{{ quote.customer.phone_number }}</p>
                <p v-if="quote.customer.address" class="text-sm text-gray-600">{{ quote.customer.address }}</p>
            </div>

            <!-- Items table -->
            <table class="w-full mb-8 border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">{{ __("Product") }}</th>
                        <th class="px-4 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">{{ __("Unit") }}</th>
                        <th class="px-4 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">{{ __("Qty") }}</th>
                        <th class="px-4 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">{{ __("Unit price") }}</th>
                        <th class="px-4 py-3 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">{{ __("Line total") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="item in quote.items" :key="item.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ item.product?.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ item.unit?.name ?? "—" }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ item.quantity }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ parseFloat(item.unit_price).toFixed(2) }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-end">{{ item.line_total?.toFixed(2) ?? (item.quantity * parseFloat(item.unit_price)).toFixed(2) }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="flex ltr:justify-end rtl:justify-start mb-8">
                <div class="w-64 space-y-2">
                    <div class="flex justify-between text-sm text-gray-700">
                        <span>{{ __("Subtotal") }}</span>
                        <span class="font-medium">{{ subtotal.toFixed(2) }} {{ quote.currency }}</span>
                    </div>
                    <div v-if="parseFloat(quote.discount) > 0" class="flex justify-between text-sm text-gray-700">
                        <span>{{ __("Discount") }}</span>
                        <span class="font-medium text-red-600">- {{ parseFloat(quote.discount).toFixed(2) }} {{ quote.currency }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold text-gray-900 border-t border-gray-200 pt-2">
                        <span>{{ __("Total") }}</span>
                        <span>{{ total.toFixed(2) }} {{ quote.currency }}</span>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="quote.notes" class="border-t border-gray-200 pt-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">{{ __("Notes") }}</p>
                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ quote.notes }}</p>
            </div>

        </div>
    </div>
</template>
