<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import { Link, useForm } from "@inertiajs/vue3";
    import { computed } from "vue";
    import DialogModal from "@/Components/DialogModal.vue";
    import { ref } from "vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import Card from "@/Pages/Purchases/Card.vue";
    import Dropdown from "@/Components/Dropdown.vue";
    import DropdownLink from "@/Components/DropdownLink.vue";

    const props = defineProps({
        invoices: Object,
        storages: Array
    });

    let movingToStorage = ref(false);

    let form = useForm({
        invoice: null,
        storage: null
    });

    let moveToStorage = (invoice) => {
        movingToStorage.value = true;
        form.invoice = invoice.id;
    };

    let closeModal = () => {
        movingToStorage.value = null;
    };

    let hasNoInvoices = computed(() => {
        return !props.invoices.data.length;
    });

    let confirmMoving = () => {
        form.put(route("stock.add", form.storage), {
            onFinish: () => closeModal()
        }).then();
    };
</script>

<template>
    <AppLayout title="Purchases">
        <div class="w-full lg:flex lg:items-end lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2
                        class="text-xl font-semibold text-gray-800 dark:text-white"
                    >
                        {{ __("Purchases") }}
                    </h2>

                    <span
                        class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                    >{{ invoices.total }} {{ __("Invoice") }}</span
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
                        :placeholder="__('Search here') + '...'"
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
                        {{ __("All") }}
                    </button>

                    <button
                        class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 bg-gray-100 sm:text-sm dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ __("With Trashed") }}
                    </button>

                    <button
                        class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 sm:text-sm dark:hover:bg-gray-800 dark:text-gray-300 hover:bg-gray-100"
                    >
                        {{ __("Trashed") }}
                    </button>
                </div>
                <div class="flex">
                    <Link
                        class="w-full px-5 py-2.5 mt-4 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg rounded-l-none sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                        :href="route('purchases.create')"
                    >
                        + {{ __("Add New Invoice") }}
                    </Link>
                    <Dropdown align="left">
                        <template #trigger>
                            <Button
                                class="inline-flex  items-center justify-center w-full px-1 py-2 mt-4 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-gray-200 rounded-lg sm:w-auto sm:mt-0 gap-x-2 rounded-r-none focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     viewBox="0 0 32 32" version="1.1" class="w-6 h-6 fill-gray-400">

                                    <g id="SVGRepo_bgCarrier" stroke-width="0" />

                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" />

                                    <g id="SVGRepo_iconCarrier">
                                        <path
                                            d="M12.15 28.012v-0.85c0.019-0.069 0.050-0.131 0.063-0.2 0.275-1.788 1.762-3.2 3.506-3.319 1.95-0.137 3.6 0.975 4.137 2.787 0.069 0.238 0.119 0.488 0.181 0.731v0.85c-0.019 0.056-0.050 0.106-0.056 0.169-0.269 1.65-1.456 2.906-3.081 3.262-0.125 0.025-0.25 0.063-0.375 0.094h-0.85c-0.056-0.019-0.113-0.050-0.169-0.056-1.625-0.262-2.862-1.419-3.237-3.025-0.037-0.156-0.081-0.3-0.119-0.444zM20.038 3.988l-0 0.85c-0.019 0.069-0.050 0.131-0.056 0.2-0.281 1.8-1.775 3.206-3.538 3.319-1.944 0.125-3.588-1-4.119-2.819-0.069-0.231-0.119-0.469-0.175-0.7v-0.85c0.019-0.056 0.050-0.106 0.063-0.162 0.3-1.625 1.244-2.688 2.819-3.194 0.206-0.069 0.425-0.106 0.637-0.162h0.85c0.056 0.019 0.113 0.050 0.169 0.056 1.631 0.269 2.863 1.419 3.238 3.025 0.038 0.15 0.075 0.294 0.113 0.437zM20.037 15.575v0.85c-0.019 0.069-0.050 0.131-0.063 0.2-0.281 1.794-1.831 3.238-3.581 3.313-1.969 0.087-3.637-1.1-4.106-2.931-0.050-0.194-0.094-0.387-0.137-0.581v-0.85c0.019-0.069 0.050-0.131 0.063-0.2 0.275-1.794 1.831-3.238 3.581-3.319 1.969-0.094 3.637 1.1 4.106 2.931 0.050 0.2 0.094 0.394 0.137 0.588z" />
                                    </g>

                                </svg>
                            </Button>
                        </template>

                        <template #content>
                            <button
                                class="block rtl:text-right w-full px-4 py-2 text-sm leading-5 text-left text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:bg-gray-100"
                                v-text="__('Return Invoice')"
                            ></button>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </div>

        <div class="py-8 space-y-8 2xl:space-y-10 md:py-12">
            <div
                v-for="invoice in invoices.data"
                :key="invoice.id"
            >
                <Card :invoice="invoice" @moveToStorage="moveToStorage" :actionTitle="__('Deliver to Storage')" :printable="true"></Card>
            </div>

            <EmptySearch :data="invoices.data"></EmptySearch>

            <div class="flex justify-center">
                <Pagination :links="invoices.links"></Pagination>
            </div>
        </div>

        <DialogModal
            :show="movingToStorage"
            @close="closeModal"
        >
            <template #title></template>
            <template #content>
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
                <SecondaryButton @click="closeModal"> {{ __("Cancel") }}</SecondaryButton>

                <PrimaryButton
                    class="ml-3 rtl:mr-3 rtl:ml-0"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click="confirmMoving"
                >
                    {{ __("Confirm") }}
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
