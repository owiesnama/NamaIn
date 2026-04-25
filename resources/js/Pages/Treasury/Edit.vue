<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { useForm, Link } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps({
    account: Object,
    account_types: Object,
    sale_points: Array,
    banks: Array,
});

const selectedBank = ref(props.banks?.find(b => b.id === props.account.bank_id) ?? null);

const form = useForm({
    name: props.account.name,
    notes: props.account.notes ?? "",
    bank_id: props.account.bank_id ?? null,
});

const submit = () => {
    form.put(route("treasury.update", props.account.id));
};
</script>

<template>
    <AppLayout :title="__('Edit Account')">
        <div class="max-w-2xl mx-auto">
            <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                        {{ __("Edit Account") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ account.name }}
                    </p>
                </div>
                <div class="mt-4 flex items-center justify-end gap-x-3 lg:mt-0">
                    <Link
                        :href="route('treasury.show', account.id)"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                    >
                        {{ __("Cancel") }}
                    </Link>
                    <PrimaryButton @click="submit" :disabled="form.processing">
                        {{ form.processing ? __("Saving...") : __("Save Changes") }}
                    </PrimaryButton>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">
                            {{ __("Account Details") }}
                        </h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <InputLabel for="name" :value="__('Account Name')" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="notes" :value="__('Notes (optional)')" />
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            ></textarea>
                            <InputError :message="form.errors.notes" class="mt-2" />
                        </div>
                        <!-- Bank selector for bank-type accounts -->
                        <div v-if="account.type === 'bank'">
                            <InputLabel for="bank_id" :value="__('Linked Bank')" />
                            <CustomSelect
                                v-model="selectedBank"
                                :options="banks ?? []"
                                label="name"
                                track-by="id"
                                :placeholder="__('Select Bank')"
                                :close-on-select="true"
                                :multiple="false"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                                class="mt-1 w-full"
                                @update:model-value="form.bank_id = selectedBank?.id ?? null"
                            />
                            <InputError :message="form.errors.bank_id" class="mt-2" />
                        </div>

                        <!-- Read-only type and currency -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">{{ __("Type") }}</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ account.type_label }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">{{ __("Currency") }}</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ account.currency }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
