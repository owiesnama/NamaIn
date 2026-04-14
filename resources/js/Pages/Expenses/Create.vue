<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
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
    receipt: null,
    is_recurring: false,
    frequency: "monthly",
    starts_at: new Date().toISOString().substr(0, 10),
    ends_at: null,
});

const formatCurrency = (amount) => {
    const validCurrency = (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD');

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

const submit = () => {
    form.post(route("expenses.store"));
};
</script>

<template>
    <AppLayout :title="__('Record Expense')">
        <section>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                {{ __("Record Expense") }}
            </h2>

            <form
                class="mt-6 flex flex-col lg:flex-row gap-8"
                @submit.prevent="submit"
            >
                <div class="flex-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-8">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Title -->
                        <div class="sm:col-span-2">
                            <InputLabel for="title" :value="__('Title')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <TextInput
                                v-model="form.title"
                                id="title"
                                type="text"
                                class="block w-full"
                                required
                                autofocus
                            />
                            <InputError :message="form.errors.title" class="mt-2" />
                        </div>

                        <!-- Amount -->
                        <div>
                            <InputLabel for="amount" :value="__('Amount')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <TextInput
                                v-model="form.amount"
                                id="amount"
                                type="number"
                                step="0.01"
                                class="block w-full"
                                required
                            />
                            <InputError :message="form.errors.amount" class="mt-2" />
                        </div>

                        <!-- Date -->
                        <div>
                            <InputLabel for="expensed_at" :value="__('Date')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <TextInput
                                v-model="form.expensed_at"
                                id="expensed_at"
                                type="date"
                                class="block w-full"
                                required
                            />
                            <InputError :message="form.errors.expensed_at" class="mt-2" />
                        </div>

                        <!-- Categories -->
                        <div class="sm:col-span-2">
                            <InputLabel for="categories" :value="__('Categories')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <select
                                v-model="form.category_ids"
                                id="categories"
                                multiple
                                class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all outline-none text-gray-900 dark:text-white h-32"
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
                            <p class="mt-1 text-xs text-gray-500 italic">{{ __("Hold Ctrl (or Cmd) to select multiple categories.") }}</p>
                        </div>

                        <!-- Notes -->
                        <div class="sm:col-span-2">
                            <InputLabel for="notes" :value="__('Notes')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <textarea
                                v-model="form.notes"
                                id="notes"
                                rows="4"
                                class="w-full px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all outline-none text-gray-900 dark:text-white"
                            ></textarea>
                            <InputError :message="form.errors.notes" class="mt-2" />
                        </div>

                        <!-- Receipt -->
                        <div class="sm:col-span-2">
                            <InputLabel for="receipt" :value="__('Receipt')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <input
                                type="file"
                                id="receipt"
                                @input="form.receipt = $event.target.files[0]"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-gray-700 dark:file:text-emerald-400"
                                accept=".jpg,.jpeg,.png,.pdf"
                            />
                            <InputError :message="form.errors.receipt" class="mt-2" />
                        </div>

                        <!-- Recurring Toggle -->
                        <div class="sm:col-span-2">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.is_recurring" class="sr-only peer">
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 dark:peer-focus:ring-emerald-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-emerald-600"></div>
                                <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">{{ __("Make this a recurring expense") }}</span>
                            </label>
                        </div>

                        <!-- Recurring Fields -->
                        <template v-if="form.is_recurring">
                            <div>
                                <InputLabel for="frequency" :value="__('Frequency')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                                <select
                                    v-model="form.frequency"
                                    id="frequency"
                                    class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all outline-none text-gray-900 dark:text-white"
                                >
                                    <option value="daily">{{ __("Daily") }}</option>
                                    <option value="weekly">{{ __("Weekly") }}</option>
                                    <option value="monthly">{{ __("Monthly") }}</option>
                                    <option value="yearly">{{ __("Yearly") }}</option>
                                </select>
                                <InputError :message="form.errors.frequency" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="starts_at" :value="__('Starts At')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                                <TextInput
                                    v-model="form.starts_at"
                                    id="starts_at"
                                    type="date"
                                    class="block w-full"
                                />
                                <InputError :message="form.errors.starts_at" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="ends_at" :value="__('Ends At (Optional)')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                                <TextInput
                                    v-model="form.ends_at"
                                    id="ends_at"
                                    type="date"
                                    class="block w-full"
                                />
                                <InputError :message="form.errors.ends_at" class="mt-2" />
                            </div>
                        </template>
                    </div>
                </div>

                <div class="lg:w-96">
                    <div class="bg-white dark:bg-gray-800 p-8 rounded-lg border border-gray-200 dark:border-gray-700 sticky top-6">
                        <div class="text-center mb-6">
                            <h3 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">
                                {{ __("Expense Summary") }}
                            </h3>
                        </div>

                        <div class="space-y-4 mb-6">
                            <div v-if="form.title" class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __("Title") }}</span>
                                <span class="font-bold text-gray-900 dark:text-white text-right max-w-[150px] truncate">{{ form.title }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __("Date") }}</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ form.expensed_at }}</span>
                            </div>
                            <div v-if="form.amount > 0" class="pt-4 border-t border-gray-100 dark:border-gray-700">
                                <div class="text-center">
                                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">{{ __("Amount") }}</span>
                                    <span class="text-3xl font-black text-red-600 dark:text-red-400 tabular-nums">
                                        {{ formatCurrency(form.amount) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button
                            class="w-full px-6 py-4 text-lg tracking-wide text-white transition-colors font-black duration-200 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50"
                            type="submit"
                            :disabled="form.processing"
                        >
                            {{ __("Save Expense") }}
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
