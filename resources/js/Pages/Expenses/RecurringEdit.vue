<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import { useForm, Link } from "@inertiajs/vue3";

const props = defineProps({
    recurring_expense: Object,
    categories: Array,
});

const form = useForm({
    title: props.recurring_expense.title,
    amount: props.recurring_expense.amount,
    currency: props.recurring_expense.currency,
    notes: props.recurring_expense.notes,
    frequency: props.recurring_expense.frequency,
    starts_at: props.recurring_expense.starts_at,
    ends_at: props.recurring_expense.ends_at,
    category_ids: props.recurring_expense.categories.map(c => c.id),
    category_objects: props.recurring_expense.categories,
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: form.currency,
    }).format(amount || 0);
};

const submit = () => {
    form.put(route("recurring-expenses.update", props.recurring_expense.id));
};
</script>

<template>
    <AppLayout :title="__('Edit Recurring Template')">
        <section>
            <div class="flex items-center gap-x-3 mb-6">
                <Link :href="route('recurring-expenses.index')" class="text-gray-500 hover:text-emerald-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                    {{ __("Edit Recurring Template") }}
                </h2>
            </div>

            <form
                class="flex flex-col lg:flex-row gap-8"
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

                        <!-- Frequency -->
                        <div>
                            <InputLabel for="frequency" :value="__('Frequency')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <VueMultiselect
                                v-model="form.frequency"
                                :options="['daily', 'weekly', 'monthly', 'yearly']"
                                :multiple="false"
                                :close-on-select="true"
                                :placeholder="__('Select Frequency')"
                                class="w-full"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                            >
                                <template #singleLabel="{ option }">
                                    {{ __(option.charAt(0).toUpperCase() + option.slice(1)) }}
                                </template>
                                <template #option="{ option }">
                                    {{ __(option.charAt(0).toUpperCase() + option.slice(1)) }}
                                </template>
                            </VueMultiselect>
                            <InputError :message="form.errors.frequency" class="mt-2" />
                        </div>

                        <!-- Starts At -->
                        <div>
                            <InputLabel for="starts_at" :value="__('Starts At')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                            <TextInput
                                v-model="form.starts_at"
                                id="starts_at"
                                type="date"
                                class="block w-full"
                                required
                            />
                            <InputError :message="form.errors.starts_at" class="mt-2" />
                        </div>

                        <!-- Ends At -->
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
                                class="w-full"
                                :select-label="__('Press enter to select')"
                                :deselect-label="__('Press enter to remove')"
                                :selected-label="__('Selected')"
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
                    </div>
                </div>

                <div class="lg:w-96">
                    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl border border-gray-200 dark:border-gray-700 sticky top-6 shadow-none">
                        <div class="text-center mb-6">
                            <h3 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">
                                {{ __("Template Summary") }}
                            </h3>
                        </div>

                        <div class="space-y-4 mb-6">
                            <div v-if="form.title" class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __("Title") }}</span>
                                <span class="font-bold text-gray-900 dark:text-white text-right max-w-[150px] truncate">{{ form.title }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __("Frequency") }}</span>
                                <span class="font-bold text-gray-900 dark:text-white uppercase text-[10px] tracking-widest">{{ __(form.frequency) }}</span>
                            </div>
                            <div v-if="form.amount > 0" class="pt-4 border-t border-gray-100 dark:border-gray-700">
                                <div class="text-center">
                                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">{{ __("Amount") }}</span>
                                    <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
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
