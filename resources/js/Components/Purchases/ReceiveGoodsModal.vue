<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    transaction: Object,
    storages: Array,
});

const show = ref(false);

const form = useForm({
    quantity: props.transaction.remaining_quantity,
    storage_id: props.transaction.storage_id || '',
    notes: '',
});

const submit = () => {
    form.post(route('purchases.receive', props.transaction.id), {
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
            class="text-emerald-600 hover:text-emerald-900 font-medium text-xs underline"
        >
            {{ __('Receive Goods') }}
        </button>

        <Modal :show="show" @close="show = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Receive Goods') }}: {{ transaction.product?.name }}
                </h2>

                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Total Ordered') }}: <span class="font-bold">{{ transaction.base_quantity }}</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Already Received') }}: <span class="font-bold">{{ transaction.received_quantity }}</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Remaining') }}: <span class="font-bold">{{ transaction.remaining_quantity }}</span>
                    </p>
                </div>

                <form @submit.prevent="submit" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="quantity" :value="__('Quantity to Receive')" />
                        <TextInput
                            id="quantity"
                            v-model.number="form.quantity"
                            type="number"
                            class="mt-1 block w-full"
                            required
                            :max="transaction.remaining_quantity"
                        />
                        <InputError :message="form.errors.quantity" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="storage_id" :value="__('Receive into Storage')" />
                        <select
                            id="storage_id"
                            v-model="form.storage_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            required
                        >
                            <option value="">{{ __('Select Storage') }}</option>
                            <option v-for="storage in storages" :key="storage.id" :value="storage.id">
                                {{ storage.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.storage_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="notes" :value="__('Notes')" />
                        <TextInput
                            id="notes"
                            v-model="form.notes"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            @click="show = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md mr-2"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <PrimaryButton :disabled="form.processing">
                            {{ __('Confirm Receipt') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </span>
</template>
