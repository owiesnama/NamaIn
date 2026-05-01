<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import FileUploader from "@/Components/FileUploader.vue";
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
    category_objects: props.expense.categories,
    notes: props.expense.notes || "",
    receipt: null,
});

const formatCurrency = (amount) => {
    const validCurrency = (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

const addCategory = (newTag) => {
    const tag = { name: newTag, id: newTag };
    form.category_objects.push(tag);
    form.category_ids = form.category_objects.map(c => c.id);
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

            <div v-if="expense.status === 'rejected'" class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl shadow-none">
                <p class="text-sm text-red-700 dark:text-red-400">
                    {{ __("This expense was rejected by") }} <strong>{{ expense.approved_by?.name }}</strong> {{ __("on") }} {{ formatDate(expense.approved_at) }}
                </p>
            </div>

            <form
                class="mt-6 flex flex-col lg:flex-row gap-8"
                @submit.prevent="submit"
            >
                <div class="flex-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-8 shadow-none">
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
                            <DatePicker
                                v-model="form.expensed_at"
                                id="expensed_at"
                                class="block w-full"
                                required
                            />
                            <InputError :message="form.errors.expensed_at" class="mt-2" />
                        </div>

                        <!-- Categories -->
                        <div class="sm:col-span-2">
                            <InputLabel for="categories" :value="__('Categories')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <VueMultiselect
                                v-model="form.category_objects"
                                :options="categories"
                                :multiple="true"
                                :close-on-select="false"
                                :clear-on-select="false"
                                :preserve-search="true"
                                :placeholder="__('Select Categories')"
                                label="name"
                                track-by="id"
                                :preselect-first="false"
                                :taggable="true"
                                :tag-placeholder="__('Press enter to create a category')"
                                class="w-full"
                                :select-label="__('Press enter to select')"
                                :deselect-label="__('Press enter to remove')"
                                :selected-label="__('Selected')"
                                @tag="addCategory"
                                @update:model-value="form.category_ids = form.category_objects.map(c => c.id)"
                            >
                                <template #noResult>
                                    <span>{{ __("No elements found. Consider changing the search query.") }}</span>
                                </template>
                            </VueMultiselect>
                            <InputError :message="form.errors.category_ids" class="mt-2" />
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
                            <FileUploader
                                v-model="form.receipt"
                                :existing-file-url="expense.receipt_path ? route('expenses.receipt', expense.id) : null"
                            />
                            <p class="mt-1 text-[10px] text-gray-500">{{ __("Uploading a new receipt will replace the old one.") }}</p>
                            <InputError :message="form.errors.receipt" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="lg:w-96">
                    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl border border-gray-200 dark:border-gray-700 sticky top-6 shadow-none">
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
