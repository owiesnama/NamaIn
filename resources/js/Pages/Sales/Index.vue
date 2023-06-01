<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import InvoiceDetails from "@/Shared/InvoiceDetails.vue";
    import { Link, useForm } from "@inertiajs/vue3";
    import { computed, ref } from "vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import DialogModal from "@/Components/DialogModal.vue";
    import InputError from "@/Components/InputError.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import Card from "@/Pages/Purchases/Card.vue";

    const props = defineProps({
        invoices: Object,
        storages: Object,
    });

    let deductingFromStorage = ref(false);
    let hasNoInvoices = computed(() => {
        return !props.invoices.data.length;
    });
    let form = useForm({
        invoice: null,
        storage: null,
    });
    let deductFromStorage = (invoice) => {
        deductingFromStorage.value = true;
        form.invoice = invoice.id;
    };

    let closeModal = () => {
        deductingFromStorage.value = null;
    };

    let confirmDeduct = () => {
        form.put(route("stock.deduct", form.storage), {
            onSuccess: () => closeModal(),
        }).then();
    };
</script>

<template>
    <AppLayout title="Sales">
        <div class="w-full lg:flex lg:items-end lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2
                        class="text-xl font-semibold text-gray-800 dark:text-white"
                    >
                        Sales
                    </h2>

                    <span
                        class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                        >{{ invoices.total }} invoices</span
                    >
                </div>

                <div class="relative flex items-center mt-4">
                    <span class="absolute">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                            />
                        </svg>
                    </span>

                    <input
                        type="text"
                        placeholder="Search here ..."
                        class="block w-full py-2 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 dark:focus:border-emerald-300 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                    />
                </div>
            </div>

            <div
                class="mt-4 sm:flex sm:items-center sm:justify-between sm:gap-x-4 lg:mt-0"
            >
                <div
                    class="flex overflow-hidden bg-white border divide-x rounded-lg md:w-auto sm:w-1/2 dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700"
                >
                    <button
                        class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 sm:text-sm dark:hover:bg-gray-800 dark:text-gray-300 hover:bg-gray-100"
                    >
                        All
                    </button>

                    <button
                        class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 bg-gray-100 sm:text-sm dark:bg-gray-800 dark:text-gray-300"
                    >
                        Untrash
                    </button>

                    <button
                        class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 sm:text-sm dark:hover:bg-gray-800 dark:text-gray-300 hover:bg-gray-100"
                    >
                        Trash
                    </button>
                </div>

                <Link
                    class="w-full px-5 py-2.5 mt-4 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    :href="route('sales.create')"
                >
                    + Add New Invoice
                </Link>
            </div>
        </div>
        
        <div class="py-8 space-y-8 2xl:space-y-10 md:py-12">
            <div
                v-for="invoice in invoices.data"
                :key="invoice.id"
            >
                <Card :invoice="invoice" @moveToStorage="deductFromStorage" actionTitle="Deduct From Storage"></Card>
            </div>

            <EmptySearch :data="invoices.data"></EmptySearch>

            <div class="flex justify-center">
                <Pagination :links="invoices.links"></Pagination>
            </div>
        </div>

        <DialogModal
            :show="deductingFromStorage"
            @close="closeModal"
        >
            <template #title></template>
            <template #content>
                <div
                    v-if="form.errors.storage"
                    class="flex items-center p-4 mb-4 border border-red-500 rounded"
                >
                    <InputError
                        class="mt-2"
                        :message="form.errors.storage"
                    />
                </div>
                <select
                    id="storage"
                    v-model="form.storage"
                    class="border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    name="storage"
                >
                    <option
                        v-for="storage in storages"
                        :key="storage.id"
                        :value="storage.id"
                        v-text="storage.name"
                    ></option>
                </select>
            </template>
            <template #footer>
                <SecondaryButton @click="closeModal"> Cancel </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click="confirmDeduct"
                >
                    Confirm
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
