<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import { Link, router, useForm } from "@inertiajs/vue3";
    import { ref, watch } from "vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import DialogModal from "@/Components/DialogModal.vue";
    import InputError from "@/Components/InputError.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import Card from "@/Pages/Purchases/Card.vue";
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
    <AppLayout title="Sales">
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

                <Link
                    class="w-full px-5 py-2.5 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    :href="route('sales.create')"
                >
                    + {{ __("Add New Invoice") }}
                </Link>
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
                    @click="confirmDeduct"
                >
                    {{ __("Confirm") }}
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
