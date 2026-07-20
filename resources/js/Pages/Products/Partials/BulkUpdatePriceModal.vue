<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch } from "vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    ids: { type: Array, required: true },
});

const emit = defineEmits(["close", "success"]);

const form = useForm({
    mode: "set",
    value: null,
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
        .patch(route("products.bulk.price"), {
            preserveScroll: true,
            onSuccess: () => emit("success"),
        });
};
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ __("Update Price") }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __("Apply a new price to the :count selected products.", { count: ids.length }) }}
            </p>

            <form @submit.prevent="submit" class="mt-6 space-y-5">
                <!-- Mode toggle -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                        {{ __("Mode") }}
                    </label>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="form.mode = 'set'"
                            :class="[
                                'px-3 py-2 text-sm font-medium rounded-lg border transition-colors duration-200',
                                form.mode === 'set'
                                    ? 'text-emerald-700 bg-emerald-50 border-emerald-300 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'
                                    : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700',
                            ]"
                        >
                            {{ __("Set price") }}
                        </button>
                        <button
                            type="button"
                            @click="form.mode = 'percent'"
                            :class="[
                                'px-3 py-2 text-sm font-medium rounded-lg border transition-colors duration-200',
                                form.mode === 'percent'
                                    ? 'text-emerald-700 bg-emerald-50 border-emerald-300 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'
                                    : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700',
                            ]"
                        >
                            {{ __("Adjust by %") }}
                        </button>
                    </div>
                </div>

                <!-- Value -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                        {{ form.mode === "percent" ? __("Percentage") : __("New Price") }}
                    </label>
                    <input
                        v-model.number="form.value"
                        type="number"
                        inputmode="decimal"
                        step="any"
                        class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        :placeholder="form.mode === 'percent' ? __('e.g. 10 or -5') : __('e.g. 250')"
                        required
                    />
                    <p v-if="form.mode === 'percent'" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        {{ __("Use a negative value to reduce the price.") }}
                    </p>
                    <p v-if="form.errors.value" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.value }}</p>
                    <p v-if="form.errors.ids" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.ids }}</p>
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
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                    >
                        {{ __("Update Price") }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
