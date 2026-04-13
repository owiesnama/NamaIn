<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { useForm } from "@inertiajs/vue3";

defineProps({
    categories: Array,
});

const form = useForm({
    title: "",
    amount: 0,
    expensed_at: new Date().toISOString().substr(0, 10),
    category_ids: [],
    notes: "",
});

const submit = () => {
    form.post(route("expenses.store"));
};
</script>

<template>
    <AppLayout title="Record Expense">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
            {{ __("Record Expense") }}
        </h2>

        <form
            class="mt-6 bg-white border-2 border-dashed rounded-lg p-6 dark:bg-gray-900 dark:border-gray-700"
            @submit.prevent="submit"
        >
            <div class="grid grid-cols-1 gap-6 mt-4 sm:grid-cols-2">
                <!-- Title -->
                <div>
                    <InputLabel for="title" :value="__('Title')" />
                    <TextInput
                        v-model="form.title"
                        id="title"
                        type="text"
                        class="block w-full mt-2"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.title" class="mt-2" />
                </div>

                <!-- Amount -->
                <div>
                    <InputLabel for="amount" :value="__('Amount')" />
                    <TextInput
                        v-model="form.amount"
                        id="amount"
                        type="number"
                        step="0.01"
                        class="block w-full mt-2"
                        required
                    />
                    <InputError :message="form.errors.amount" class="mt-2" />
                </div>

                <!-- Date -->
                <div>
                    <InputLabel for="expensed_at" :value="__('Date')" />
                    <TextInput
                        v-model="form.expensed_at"
                        id="expensed_at"
                        type="date"
                        class="block w-full mt-2"
                        required
                    />
                    <InputError :message="form.errors.expensed_at" class="mt-2" />
                </div>

                <!-- Categories -->
                <div>
                    <InputLabel for="categories" :value="__('Categories')" />
                    <select
                        v-model="form.category_ids"
                        id="categories"
                        multiple
                        class="block w-full px-4 py-2 mt-2 text-gray-700 bg-white border border-gray-200 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:ring-opacity-40 dark:focus:border-emerald-300 focus:outline-none focus:ring h-24"
                    >
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.category_ids" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-500">{{ __("Hold Ctrl (or Cmd) to select multiple categories.") }}</p>
                </div>

                <!-- Notes -->
                <div class="sm:col-span-2">
                    <InputLabel for="notes" :value="__('Notes')" />
                    <textarea
                        v-model="form.notes"
                        id="notes"
                        class="block w-full px-4 py-2 mt-2 text-gray-700 bg-white border border-gray-200 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:ring-opacity-40 dark:focus:border-emerald-300 focus:outline-none focus:ring"
                        rows="3"
                    ></textarea>
                    <InputError :message="form.errors.notes" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <PrimaryButton
                    class="bg-emerald-500 hover:bg-emerald-600"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ __("Save Expense") }}
                </PrimaryButton>
            </div>
        </form>
    </AppLayout>
</template>
