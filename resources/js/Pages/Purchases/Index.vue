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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Purchases
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Link
                    as="button"
                    :href="route('purchases.create')"
                    class="border py-3 px-6 rounded-lg bg-gray-50 font-semibold hover:bg-gray-100"
                    >New Purchase Invoice</Link
                >
                <div
                    v-for="invoice in invoices.data"
                    :key="invoice.id"
                    class="bg-white overflow-hidden border sm:rounded p-4 mt-2"
                >
                    <div class="flex space-x-2 items-center justify-between">
                        <div>
                            <span
                                class="text-white bg-green-500 px-5 py-1 rounded-xl mr-4"
                                >In-stock</span
                            >
                            <span
                                class="font-bold"
                                v-text="invoice.total"
                            ></span>
                        </div>
                        <PrimaryButton
                            v-if="!invoice.has_used"
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
                    class="bg-white overflow-hidden border sm:rounded p-4 mt-2"
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
                    @click="confirmMoving"
                >
                    Confirm
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
