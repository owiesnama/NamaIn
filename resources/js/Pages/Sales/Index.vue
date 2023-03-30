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
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Sales
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Link
                    as="button"
                    :href="route('sales.create')"
                    class="border py-3 px-6 rounded-lg bg-gray-50 font-semibold hover:bg-gray-100"
                    >New Sale</Link
                >
                <div
                    v-for="invoice in invoices.data"
                    :key="invoice.id"
                    class="bg-white overflow-hidden border sm:rounded p-4 mt-2"
                >
                    <div class="flex justify-between">
                        <div class="flex space-x-2">
                            <span
                                class="text-white bg-green-500 px-5 py-1 rounded-xl"
                                >In-stock</span
                            >
                            <span
                                class="font-bold"
                                v-text="invoice.total"
                            ></span>
                        </div>

                        <PrimaryButton
                            v-if="! invoice.locked"
                            @click="deductFromStorage(invoice)"
                        >
                            Deduct From Storage
                        </PrimaryButton>
                    </div>
                    <InvoiceDetails
                        :invoice="invoice"
                        class="w-full mt-3"
                    />
                </div>
                <div
                    v-if="hasNoInvoices"
                    class="bg-white overflow-hidden border sm:rounded p-4 mt-2"
                >
                    <p class="text-gray-700">
                        <strong>Opps</strong>, Seems like there no sales for now
                    </p>
                </div>
            </div>
            <Pagination :links="invoices.links"></Pagination>
        </div>
        <DialogModal
            :show="deductingFromStorage"
            @close="closeModal"
        >
            <template #title></template>
            <template #content>
                <div
                    v-if="form.errors.storage"
                    class="p-4 border border-red-500 rounded mb-4 flex items-center"
                >
                    <InputError
                        class="mt-2"
                        :message="form.errors.storage"
                    />
                </div>
                <select
                    id="storage"
                    v-model="form.storage"
                    class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
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
