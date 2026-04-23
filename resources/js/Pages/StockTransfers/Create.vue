<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { useForm, Link } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

defineProps({
    storages: Array,
    products: Array,
});

const form = useForm({
    from_storage_id: new URLSearchParams(window.location.search).get('from_storage_id') || '',
    to_storage_id: '',
    notes: '',
    items: [
        { product_id: '', quantity: 1 }
    ]
});

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const addItem = () => {
    form.items.push({ product_id: '', quantity: 1 });
};

const submit = () => {
    form.post(route('stock-transfers.store'));
};
</script>

<template>
    <AppLayout :title="__('New Stock Transfer')">
        <div class="max-w-4xl mx-auto">
            <!-- Page Header -->
            <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                            {{ __("New Stock Transfer") }}
                        </h2>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __("Move inventory between your storages.") }}
                    </p>
                </div>
                <div class="mt-4 flex items-center justify-end gap-x-3 lg:mt-0">
                    <Link
                        :href="route('stock-transfers.index')"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                    >
                        {{ __("Cancel") }}
                    </Link>
                    <PrimaryButton @click="submit" :disabled="form.processing">
                        <svg v-if="form.processing" class="animate-spin h-4 w-4 ltr:mr-2 rtl:ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ form.processing ? __("Executing...") : __("Execute Transfer") }}
                    </PrimaryButton>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Source & Destination -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">
                            {{ __("Transfer Route") }}
                        </h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="from_storage_id" :value="__('Source Storage')" />
                            <CustomSelect
                                v-model="form.from_storage_id"
                                :options="storages"
                                label="name"
                                track-by="id"
                                :placeholder="__('Select Source')"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.from_storage_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="to_storage_id" :value="__('Destination Storage')" />
                            <CustomSelect
                                v-model="form.to_storage_id"
                                :options="storages"
                                label="name"
                                track-by="id"
                                :placeholder="__('Select Destination')"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.to_storage_id" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">
                            {{ __("Items to Transfer") }}
                        </h3>
                        <button
                            type="button"
                            @click="addItem"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
                        >
                            <span class="w-5 h-5 rounded-full bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            {{ __("Add Item") }}
                        </button>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-800" v-auto-animate>
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="px-6 py-4 flex items-end gap-4"
                        >
                            <div class="flex-1 min-w-0">
                                <InputLabel :value="__('Product')" class="mb-1" />
                                <CustomSelect
                                    v-model="item.product_id"
                                    :options="products"
                                    label="name"
                                    track-by="id"
                                    :placeholder="__('Select Product')"
                                    class="w-full"
                                />
                                <InputError :message="form.errors[`items.${index}.product_id`]" class="mt-1" />
                            </div>

                            <div class="w-32 shrink-0">
                                <InputLabel :value="__('Quantity')" class="mb-1" />
                                <TextInput
                                    v-model.number="item.quantity"
                                    type="number"
                                    class="w-full"
                                    min="1"
                                    required
                                />
                                <InputError :message="form.errors[`items.${index}.quantity`]" class="mt-1" />
                            </div>

                            <div class="shrink-0 pb-0.5">
                                <button
                                    v-if="form.items.length > 1"
                                    type="button"
                                    @click="removeItem(index)"
                                    class="p-1.5 text-gray-300 dark:text-gray-600 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div v-else class="w-7" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">
                            {{ __("Notes") }}
                        </h3>
                    </div>
                    <div class="p-6">
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                            :placeholder="__('Any additional information about this transfer...')"
                        ></textarea>
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
