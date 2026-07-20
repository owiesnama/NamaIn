<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch } from "vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    ids: { type: Array, required: true },
    storages: { type: Array, required: true },
});

const emit = defineEmits(["close", "success"]);

const form = useForm({
    storage_id: null,
    delta: null,
    type: "manual",
    notes: "",
});

watch(
    () => props.show,
    (open) => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    }
);

const submit = () => {
    form
        .transform((data) => ({ ...data, ids: props.ids }))
        .post(route("products.bulk.stock"), {
            preserveScroll: true,
            onSuccess: () => emit("success"),
        });
};
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ __("Adjust Stock") }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __("Apply a quantity change to the :count selected products at one storage. Services are skipped.", { count: ids.length }) }}
            </p>

            <form @submit.prevent="submit" class="mt-6 space-y-5">
                <!-- Storage selector -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                        {{ __("Storage Location") }}
                    </label>
                    <select
                        v-model.number="form.storage_id"
                        class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        required
                    >
                        <option :value="null" disabled>{{ __("Select a storage...") }}</option>
                        <option v-for="storage in storages" :key="storage.id" :value="storage.id">
                            {{ storage.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.storage_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.storage_id }}</p>
                </div>

                <!-- Delta -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                        {{ __("Quantity Change") }}
                    </label>
                    <input
                        v-model.number="form.delta"
                        type="number"
                        inputmode="numeric"
                        step="1"
                        class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        :placeholder="__('e.g. 5 to add, -5 to remove')"
                        required
                    />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        {{ __("Applied to each product's current quantity at the selected storage.") }}
                    </p>
                    <p v-if="form.errors.delta" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.delta }}</p>
                </div>

                <!-- Adjustment type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                        {{ __("Adjustment Type") }}
                    </label>
                    <select
                        v-model="form.type"
                        class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                    >
                        <option value="manual">{{ __("Manual Adjustment") }}</option>
                        <option value="damage">{{ __("Damage") }}</option>
                        <option value="loss">{{ __("Loss") }}</option>
                        <option value="correction">{{ __("Correction") }}</option>
                    </select>
                    <p v-if="form.errors.type" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.type }}</p>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                        {{ __("Notes") }}
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
                        @click="emit('close')"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                    >
                        {{ __("Cancel") }}
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || form.storage_id === null"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                    >
                        {{ __("Apply") }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
