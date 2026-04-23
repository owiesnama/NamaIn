<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    storage: {
        type: Object,
        required: true,
    },
    product: {
        type: Object,
        required: true,
    },
    currentQuantity: {
        type: Number,
        required: true,
    }
});

const show = ref(false);

const form = useForm({
    new_quantity: props.currentQuantity,
    type: 'manual',
    notes: '',
});

const submit = () => {
    form.post(route('storages.adjust', [props.storage.id, props.product.id]), {
        preserveScroll: true,
        onSuccess: () => {
            show.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <span>
        <button
            @click="show = true"
            class="text-emerald-600 hover:text-emerald-900 dark:hover:text-emerald-400 font-medium text-sm"
        >
            {{ __('Adjust') }}
        </button>

        <Modal :show="show" @close="show = false">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ __('Adjust Stock') }}: {{ product.name }}
                </h2>

                <div class="mt-4 p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-100 dark:border-emerald-800">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-emerald-700 dark:text-emerald-400 font-medium">{{ __('Current Quantity') }}</span>
                        <span class="text-xl font-bold text-emerald-800 dark:text-emerald-300">{{ currentQuantity }}</span>
                    </div>
                </div>

                <form @submit.prevent="submit" class="mt-8 space-y-6">
                    <div>
                        <InputLabel for="new_quantity" :value="__('New Quantity')" />
                        <TextInput
                            id="new_quantity"
                            v-model.number="form.new_quantity"
                            type="number"
                            class="mt-1 block w-full rounded-xl py-3"
                            required
                        />
                        <InputError :message="form.errors.new_quantity" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="type" :value="__('Adjustment Type')" />
                        <select
                            id="type"
                            v-model="form.type"
                            class="mt-1 block w-full px-3 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option value="manual">{{ __('Manual Adjustment') }}</option>
                            <option value="damage">{{ __('Damage') }}</option>
                            <option value="loss">{{ __('Loss') }}</option>
                            <option value="correction">{{ __('Correction') }}</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="notes" :value="__('Notes')" />
                        <TextInput
                            id="notes"
                            v-model="form.notes"
                            type="text"
                            class="mt-1 block w-full rounded-xl py-3"
                            :placeholder="__('Reason for adjustment...')"
                        />
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>

                    <div class="mt-10 flex justify-end gap-x-3">
                        <button
                            type="button"
                            @click="show = false"
                            class="px-6 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <PrimaryButton :disabled="form.processing" class="px-8 rounded-lg shadow-sm">
                            {{ __('Record Adjustment') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </span>
</template>
