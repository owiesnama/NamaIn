<script setup>
import { computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

const props = defineProps({
    show: Boolean,
    sessionId: Number,
    sessionStats: Object,
    currency: String,
});

const emit = defineEmits(['close']);

const fmt = (val) => `${Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${props.currency}`;

const form = useForm({
    session_id: props.sessionId,
    closing_float: '',
});

const liveVariance = computed(() => {
    const actual = parseFloat(form.closing_float);
    if (isNaN(actual) || form.closing_float === '') return null;
    return actual - (props.sessionStats?.expected_closing_float ?? 0);
});

const varianceLabel = computed(() => {
    if (liveVariance.value === null) return null;
    return liveVariance.value >= 0 ? `+${liveVariance.value.toFixed(2)}` : liveVariance.value.toFixed(2);
});

const closeSession = () => form.post(route('pos.close'));
</script>

<template>
    <Modal :show="show" @close="emit('close')">
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
            <div v-if="sessionStats" class="mb-5 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Opening Float') }}</span>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ fmt(sessionStats.opening_float) }}</span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Cash Sales This Session') }}</span>
                        <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">+ {{ fmt(sessionStats.cash_sales_total) }}</span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/40">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Expected Closing Float') }}</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ fmt(sessionStats.expected_closing_float) }}</span>
                    </div>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <InputLabel for="closing_float" :value="__('Actual Cash in Hand')" />
                    <TextInput v-model="form.closing_float" id="closing_float" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-xl" :placeholder="sessionStats ? String(sessionStats.expected_closing_float) : '0.00'" />
                </div>
                <div v-if="liveVariance !== null" class="flex items-center justify-between rounded-xl px-4 py-3 transition-colors" :class="liveVariance < 0 ? 'bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800' : 'bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800'">
                    <span class="text-sm font-medium" :class="liveVariance < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'">{{ __('Variance') }}</span>
                    <span class="text-sm font-bold" :class="liveVariance < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'">{{ varianceLabel }}</span>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-x-3">
                <button @click="emit('close')" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">{{ __('Cancel') }}</button>
                <PrimaryButton @click="closeSession" :disabled="form.processing" class="bg-red-600 hover:bg-red-700 focus:ring-red-500">{{ __('Confirm & Close') }}</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
