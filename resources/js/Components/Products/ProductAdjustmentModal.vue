<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    product: { type: Object, required: true },
    storages: { type: Array, required: true },
});

const show = ref(false);
const selectedStorageId = ref(null);

const currentQuantity = computed(() => {
    if (!selectedStorageId.value) return null;
    const entry = props.product.stock?.find(s => s.id === selectedStorageId.value);
    return entry?.pivot?.quantity ?? 0;
});

const form = useForm({
    new_quantity: 0,
    type: 'manual',
    notes: '',
});

watch(selectedStorageId, () => {
    if (selectedStorageId.value !== null) {
        form.new_quantity = currentQuantity.value;
    }
});

const open = () => {
    selectedStorageId.value = null;
    form.reset();
    show.value = true;
};

const submit = () => {
    form.post(route('storages.adjust', [selectedStorageId.value, props.product.id]), {
        preserveScroll: true,
        onSuccess: () => {
            show.value = false;
        },
    });
};
</script>

<template>
    <span>
        <button
            @click.stop="open"
            class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:text-gray-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20 rounded-lg transition-all"
            :title="__('Adjust Stock')"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.59 1.202a5.99 5.99 0 01-2.031.352 5.99 5.99 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z" />
            </svg>
        </button>

        <Modal :show="show" @close="show = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('Adjust Stock') }}: {{ product.name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Select a storage location and set the new quantity.') }}
                </p>

                <form @submit.prevent="submit" class="mt-6 space-y-5">
                    <!-- Storage selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                            {{ __('Storage Location') }}
                        </label>
                        <select
                            v-model.number="selectedStorageId"
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            required
                        >
                            <option :value="null" disabled>{{ __('Select a storage...') }}</option>
                            <option v-for="storage in storages" :key="storage.id" :value="storage.id">
                                {{ storage.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Current quantity indicator -->
                    <div
                        v-if="selectedStorageId !== null"
                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-lg"
                    >
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Current Quantity') }}</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ currentQuantity }} <span class="font-normal text-gray-400">{{ product.unit?.name }}</span>
                        </span>
                    </div>

                    <!-- New quantity -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                            {{ __('New Quantity') }}
                        </label>
                        <input
                            v-model.number="form.new_quantity"
                            type="number"
                            min="0"
                            :disabled="selectedStorageId === null"
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            required
                        />
                        <p v-if="form.errors.new_quantity" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.new_quantity }}</p>
                    </div>

                    <!-- Adjustment type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                            {{ __('Adjustment Type') }}
                        </label>
                        <select
                            v-model="form.type"
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option value="manual">{{ __('Manual Adjustment') }}</option>
                            <option value="damage">{{ __('Damage') }}</option>
                            <option value="loss">{{ __('Loss') }}</option>
                            <option value="correction">{{ __('Correction') }}</option>
                        </select>
                        <p v-if="form.errors.type" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.type }}</p>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                            {{ __('Notes') }}
                        </label>
                        <input
                            v-model="form.notes"
                            type="text"
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            :placeholder="__('Reason for adjustment...')"
                        />
                        <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.notes }}</p>
                    </div>

                    <div class="flex justify-end gap-x-3 pt-2">
                        <button
                            type="button"
                            @click="show = false"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || selectedStorageId === null"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        >
                            {{ __('Record Adjustment') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </span>
</template>
