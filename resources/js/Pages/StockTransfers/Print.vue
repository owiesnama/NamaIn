<script setup>
import { computed, onMounted } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    transfer: Object,
});

const dir = computed(() => (usePage().props.locale === "ar" ? "rtl" : "ltr"));

const lines = computed(() => props.transfer.lines || []);

const totalQuantity = computed(() =>
    lines.value.reduce((sum, line) => sum + Number(line.quantity || 0), 0),
);

onMounted(() => {
    window.print();
});
</script>

<template>
    <div :dir="dir" class="min-h-screen bg-white p-8 print:p-0 text-gray-900">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="flex items-start justify-between mb-8 border-b border-gray-200 pb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __("Stock Transfer") }}</h1>
                    <p class="mt-1 text-sm text-gray-500">#{{ transfer.id }}</p>
                </div>
                <div class="text-end">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Transferred At") }}
                    </p>
                    <p class="text-sm text-gray-900">
                        {{ (transfer.transferred_at ?? transfer.created_at)?.slice(0, 10) }}
                    </p>
                </div>
            </div>

            <!-- From → To -->
            <div class="grid grid-cols-2 gap-6 mb-8 text-sm">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("From") }}
                    </p>
                    <p class="font-semibold text-gray-900">{{ transfer.from_storage?.name }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("To") }}
                    </p>
                    <p class="font-semibold text-gray-900">{{ transfer.to_storage?.name }}</p>
                </div>
            </div>

            <!-- Meta strip -->
            <div class="grid grid-cols-2 gap-6 mb-8 text-sm">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Created By") }}
                    </p>
                    <p class="text-gray-900">{{ transfer.creator?.name ?? "—" }}</p>
                </div>
                <div v-if="transfer.notes">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                        {{ __("Notes") }}
                    </p>
                    <p class="text-gray-900 whitespace-pre-line">{{ transfer.notes }}</p>
                </div>
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
                        <th class="px-3 py-3 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-500">
                            {{ __("Quantity") }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="(line, idx) in lines" :key="line.id">
                        <td class="px-3 py-2.5 text-xs text-gray-400">{{ idx + 1 }}</td>
                        <td class="px-3 py-2.5 text-sm font-semibold text-gray-900">
                            {{ line.product?.name }}
                        </td>
                        <td class="px-3 py-2.5 text-sm text-gray-900 text-end whitespace-nowrap">
                            {{ line.quantity }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td colspan="2" class="px-3 py-2.5 text-sm font-bold text-gray-900 text-end">
                            {{ __("Total") }}
                        </td>
                        <td class="px-3 py-2.5 text-sm font-bold text-gray-900 text-end whitespace-nowrap">
                            {{ totalQuantity }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- Footer -->
            <div class="border-t border-gray-200 pt-4">
                <p class="text-xs text-gray-500">{{ __("Stock transfer record") }}</p>
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
