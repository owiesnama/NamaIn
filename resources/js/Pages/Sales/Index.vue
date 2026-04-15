<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import { Link, router, useForm } from "@inertiajs/vue3";
    import { ref, watch, computed } from "vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import DialogModal from "@/Components/DialogModal.vue";
    import InputError from "@/Components/InputError.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import Card from "@/Pages/Purchases/Card.vue";
    import Dropdown from "@/Components/Dropdown.vue";
    import DropdownLink from "@/Components/DropdownLink.vue";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { debounce } from "lodash";
    import axios from "axios";
    import VueMultiselect from "vue-multiselect";

    import CustomSelect from "@/Components/CustomSelect.vue";

    defineProps({
        invoices: Object,
        storages: Array
    });

    const isRtl = computed(() => document.documentElement.dir === 'rtl' || document.documentElement.lang === 'ar');

    const selectedStorage = ref(null);
    const showInvoiceSelector = ref(false);

    const showSidebar = ref(true);

    const invoicesToReturn = ref([]);
    const searchingInvoices = ref(false);
    const searchReturnQuery = ref("");

    const selectedInvoiceForReturn = ref(null);

    const searchInvoicesForReturn = debounce(async (query = "") => {
        searchingInvoices.value = true;
        try {
            const response = await axios.get(route('invoices.search-for-return'), {
                params: {
                    type: 'sale',
                    search: query
                }
            });
            invoicesToReturn.value = response.data;
        } catch (error) {
            console.error(error);
        } finally {
            searchingInvoices.value = false;
        }
    }, 500);

    watch(searchReturnQuery, (newQuery) => {
        searchInvoicesForReturn(newQuery);
    });

    watch(showInvoiceSelector, (value) => {
        if (value) {
            searchInvoicesForReturn("");
        }
    });

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

    const deductingFromStorage = ref(false);

    let form = useForm({
        invoice: null,
        storage: null
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
            onSuccess: () => closeModal()
        }).then();
    };

    const handleInvoiceSelect = (invoice) => {
        if (invoice && invoice.return_url) {
            router.visit(invoice.return_url);
        }
    };

    watch(
        filters,
        debounce(function() {
            router.get(
                route("sales.index"),
                filters.value,
                { preserveState: true }
            );
        }, 300),
        { deep: true }
    );
</script>

<template>
    <AppLayout :title="__('Sales')">
        <div class="w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2
                        class="text-xl font-semibold text-gray-800 dark:text-white"
                    >
                        {{ __("Sales") }}
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

                <div class="flex items-stretch">
                    <Link
                        class="px-5 py-2.5 flex items-center justify-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-s-lg bg-emerald-500 shrink-0 hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600 border-e border-emerald-400"
                        :href="route('sales.create')"
                    >
                        + {{ __("Add New Invoice") }}
                    </Link>
                    <Dropdown :align="isRtl ? 'left' : 'right'" width="48">
                        <template #trigger>
                            <button
                                class="px-2 py-2.5 bg-emerald-500 text-white rounded-e-lg hover:bg-emerald-600 transition-colors duration-200 focus:outline-none h-full flex items-center justify-center"
                                :title="__('More Actions')"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink as="button" @click="showInvoiceSelector = true">
                                {{ __("Create Return") }}
                            </DropdownLink>
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
                        <Card
                            :invoice="invoice"
                            @moveToStorage="deductFromStorage"
                            :actionTitle="__('Deduct From Storage')"
                            :printable="true"
                        ></Card>
                    </div>

                    <EmptySearch :data="invoices.data"></EmptySearch>

                    <div class="flex justify-center mt-8">
                        <Pagination :links="invoices.links"></Pagination>
                    </div>
                </div>
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
                <VueMultiselect
                    v-model="selectedStorage"
                    :options="storages"
                    :multiple="false"
                    :close-on-select="true"
                    :placeholder="__('Select Storage')"
                    label="name"
                    track-by="id"
                    class="w-full"
                    :select-label="''"
                    :deselect-label="''"
                    :selected-label="__('Selected')"
                    @update:model-value="form.storage = selectedStorage?.id || null"
                />
            </template>
            <template #footer>
                <SecondaryButton @click="closeModal"> {{ __("Cancel") }}</SecondaryButton>

                <PrimaryButton
                    class="ml-3 rtl:mr-3 rtl:ml-0"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click="confirmDeduct"
                >
                    {{ __("Confirm") }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <DialogModal :show="showInvoiceSelector" @close="showInvoiceSelector = false">
            <template #title>
                {{ __("Search Invoice to Return") }}
            </template>
            <template #content>
                <div class="mt-4">
                    <CustomSelect
                        v-model="selectedInvoiceForReturn"
                        :options="invoicesToReturn"
                        label="serial_number"
                        track-by="id"
                        remote
                        :placeholder="__('Search by Serial Number or Customer Name...')"
                        @search-change="searchInvoicesForReturn"
                        @update:model-value="handleInvoiceSelect"
                    >
                        <template #option="{ option }">
                            <div class="flex items-center justify-between w-full">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        #{{ option.serial_number }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ option.invocable_name }} • {{ option.date }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-emerald-600">
                                        {{ option.total }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </CustomSelect>

                    <div v-if="searchingInvoices" class="mt-4 text-center">
                        <span class="text-sm text-gray-500">{{ __("Searching...") }}</span>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showInvoiceSelector = false">
                    {{ __("Close") }}
                </SecondaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
