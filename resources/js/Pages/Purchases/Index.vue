<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import { Link, useForm, router } from "@inertiajs/vue3";
    import { watch, ref } from "vue";
    import DialogModal from "@/Components/DialogModal.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import Card from "@/Pages/Purchases/Card.vue";
    import Dropdown from "@/Components/Dropdown.vue";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { debounce } from "lodash";

    defineProps({
        invoices: Object,
        storages: Array
    });

    const showSidebar = ref(true);

    const filters = ref({
        search: useQueryString("search").value,
        status: useQueryString("status").value,
        sort_by: useQueryString("sort_by").value || "created_at",
        sort_order: useQueryString("sort_order").value || "desc"
    });

    const resetFilters = () => {
        filters.value = {
            search: null,
            status: null,
            sort_by: "created_at",
            sort_order: "desc"
        };
    };

    const sortByOptions = [
        { label: __("Date"), value: "created_at" },
        { label: __("Invoice Number"), value: "id" },
        { label: __("Total Amount"), value: "total" },
    ];

    watch(
        filters,
        debounce(function() {
            router.get(route("purchases.index"), filters.value, { preserveState: true });
        }, 300),
        { deep: true }
    );

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

    let confirmMoving = () => {
        form.put(route("stock.add", form.storage), {
            onFinish: () => closeModal()
        }).then();
    };
</script>

<template>
    <AppLayout title="Purchases">
        <div class="w-full lg:flex lg:items-center lg:justify-between">
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
            </div>

            <div
                class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0"
            >
                <button
                    @click="showSidebar = !showSidebar"
                    :class="[
                        'inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700 transition-colors',
                        showSidebar ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20' : ''
                    ]"
                    :title="__('Filters')"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                    </svg>
                </button>

                <div class="flex">
                    <Link
                        class="w-full px-5 py-2.5 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg rounded-l-none sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                        :href="route('purchases.create')"
                    >
                        + {{ __("Add New Invoice") }}
                    </Link>
                    <Dropdown align="left">
                        <template #trigger>
                            <button
                                class="inline-flex items-center justify-center w-full px-1 py-2 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-gray-200 rounded-lg sm:w-auto gap-x-2 rounded-r-none focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 focus:outline-none dark:bg-gray-900 dark:border-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     viewBox="0 0 32 32" version="1.1" class="w-6 h-6 fill-gray-400">
                                    <g id="SVGRepo_iconCarrier">
                                        <path
                                            d="M12.15 28.012v-0.85c0.019-0.069 0.050-0.131 0.063-0.2 0.275-1.788 1.762-3.2 3.506-3.319 1.95-0.137 3.6 0.975 4.137 2.787 0.069 0.238 0.119 0.488 0.181 0.731v0.85c-0.019 0.056-0.050 0.106-0.056 0.169-0.269 1.65-1.456 2.906-3.081 3.262-0.125 0.025-0.25 0.063-0.375 0.094h-0.85c-0.056-0.019-0.113-0.050-0.169-0.056-1.625-0.262-2.862-1.419-3.237-3.025-0.037-0.156-0.081-0.3-0.119-0.444zM20.038 3.988l-0 0.85c-0.019 0.069-0.050 0.131-0.056 0.2-0.281 1.8-1.775 3.206-3.538 3.319-1.944 0.125-3.588-1-4.119-2.819-0.069-0.231-0.119-0.469-0.175-0.7v-0.85c0.019-0.056 0.050-0.106 0.063-0.162 0.3-1.625 1.244-2.688 2.819-3.194 0.206-0.069 0.425-0.106 0.637-0.162h0.85c0.056 0.019 0.113 0.050 0.169 0.056 1.631 0.269 2.863 1.419 3.238 3.025 0.038 0.15 0.075 0.294 0.113 0.437zM20.037 15.575v0.85c-0.019 0.069-0.050 0.131-0.063 0.2-0.281 1.794-1.831 3.238-3.581 3.313-1.969 0.087-3.637-1.1-4.106-2.931-0.050-0.194-0.094-0.387-0.137-0.581v-0.85c0.019-0.069 0.050-0.131 0.063-0.2 0.275-1.794 1.831-3.238 3.581-3.319 1.969-0.094 3.637 1.1 4.106 2.931 0.050 0.2 0.094 0.394 0.137 0.588z" />
                                    </g>
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <button
                                class="block rtl:text-right w-full px-4 py-2 text-sm leading-5 text-left text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                v-text="__('Return Invoice')"
                            ></button>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </div>

        <div class="flex flex-col mt-8 lg:flex-row lg:gap-x-6">
            <!-- Sidebar -->
            <FilterSidebar
                v-if="showSidebar"
                v-model:filters="filters"
                :sort-by-options="sortByOptions"
                :all-label="__('All Invoices')"
                @reset="resetFilters"
            />

            <!-- Invoices List -->
            <div class="flex-1 min-w-0">
                <div class="space-y-6">
                    <div
                        v-for="invoice in invoices.data"
                        :key="invoice.id"
                    >
                        <Card :invoice="invoice" @moveToStorage="moveToStorage" :actionTitle="__('Deliver to Storage')" :printable="true"></Card>
                    </div>

                    <EmptySearch :data="invoices.data"></EmptySearch>

                    <div class="flex justify-center mt-8">
                        <Pagination :links="invoices.links"></Pagination>
                    </div>
                </div>
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
