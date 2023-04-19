<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import InvoiceDetails from "@/Shared/InvoiceDetails.vue";
    import { Link, useForm } from "@inertiajs/vue3";
    import { computed } from "vue";
    import DialogModal from "@/Components/DialogModal.vue";
    import { ref } from "vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    
    const props = defineProps({
        invoices: Object,
        storages: Array,
    });

    let movingToStorage = ref(false);

    let form = useForm({
        invoice: null,
        storage: null,
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
            onFinish: () => closeModal(),
        }).then();
    };
</script>

<template>
    <AppLayout title="Purchases">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Purchases
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <Link
                    as="button"
                    :href="route('purchases.create')"
                    class="px-6 py-3 font-semibold border rounded-lg bg-gray-50 hover:bg-gray-100"
                    >New Purchase Invoice</Link
                >
                <div
                    v-for="invoice in invoices.data"
                    :key="invoice.id"
                    class="p-4 mt-2 overflow-hidden bg-white border sm:rounded"
                >
                    <div class="flex items-center justify-between space-x-2">
                        <div>
                            <span
                                class="px-5 py-1 mr-4 text-white bg-green-500 rounded-xl"
                                >In-stock</span
                            >
                            <span
                                class="font-bold"
                                v-text="invoice.total"
                            ></span>
                        </div>
                        <PrimaryButton
                            v-if="!invoice.locked"
                            @click="moveToStorage(invoice)"
                        >
                            Deliver to storage
                        </PrimaryButton>
                    </div>
                    <InvoiceDetails
                        :invoice="invoice"
                        class="w-full mt-3"
                    />
                </div>
                <div
                    v-if="hasNoInvoices"
                    class="p-4 mt-2 overflow-hidden bg-white border sm:rounded"
                >
                    <p class="text-gray-700">
                        <strong>Opps</strong>, Seems like there no purchases for
                        now
                    </p>
                </div>
            </div>
            <Pagination :links="invoices.links"></Pagination>
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
                <SecondaryButton @click="closeModal"> Cancel </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                    @click="confirmMoving"
                >
                    Confirm
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
