<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
    expense: Object,
    categories: Array,
});

const form = useForm({
    title: props.expense.title,
    amount: props.expense.amount,
    expensed_at: props.expense.expensed_at?.split('T')[0] || props.expense.expensed_at,
    category_ids: props.expense.categories.map(c => c.id),
    notes: props.expense.notes || "",
    receipt: null,
});

const formatCurrency = (amount) => {
    const validCurrency = (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD');

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

const submit = () => {
    form.post(route("expenses.update", props.expense.id), {
        onSuccess: () => {},
        forceFormData: true,
    });
};

const formatDate = (date) => {
    if (!date) return "";
    return new Intl.DateTimeFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
};
</script>

<template>
    <AppLayout :title="__('Edit Expense')">
        <section>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                    {{ __("Edit Expense") }}
                </h2>

                <div class="flex items-center gap-2">
                    <span :class="[
                        'px-3 py-1 text-xs font-semibold rounded-full',
                        expense.status === 'approved' ? 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' :
                        expense.status === 'rejected' ? 'text-red-700 bg-red-100 dark:bg-red-900/30 dark:text-red-400' :
                        'text-amber-700 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400'
                    ]">
                        {{ __(expense.status.charAt(0).toUpperCase() + expense.status.slice(1)) }}
                    </span>
                </div>
            </div>

            <div v-if="expense.status === 'rejected'" class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-sm text-red-700 dark:text-red-400">
                    {{ __("This expense was rejected by") }} <strong>{{ expense.approved_by?.name }}</strong> {{ __("on") }} {{ formatDate(expense.approved_at) }}
                </p>
            </div>

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
                            <div v-if="expense.receipt_path" class="mb-2 flex items-center gap-2 text-sm text-emerald-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                <a :href="route('expenses.receipt', expense.id)" target="_blank" class="hover:underline font-medium">{{ __("View Current Receipt") }}</a>
                            </div>
                            <input
                                type="file"
                                id="receipt"
                                @input="form.receipt = $event.target.files[0]"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-gray-700 dark:file:text-emerald-400"
                                accept=".jpg,.jpeg,.png,.pdf"
                            />
                            <p class="mt-1 text-[10px] text-gray-500">{{ __("Uploading a new receipt will replace the old one.") }}</p>
                            <InputError :message="form.errors.receipt" class="mt-2" />
                        </div>
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
                            {{ __("Save Changes") }}
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
