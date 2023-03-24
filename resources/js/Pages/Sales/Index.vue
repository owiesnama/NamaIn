<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import InvoiceDetails from "@/Shared/InvoiceDetails.vue";
    import { Link } from "@inertiajs/vue3";
    import { computed } from "vue";
    const props = defineProps({
        invoices: Object,
    });

    let hasNoInvoices = computed(() => {
        return !props.invoices.data.length;
    });
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
                    <div class="flex space-x-2 items-center">
                        <span
                            class="text-white bg-green-500 px-5 py-1 rounded-xl"
                            >In-stock</span
                        >
                        <span
                            class="font-bold"
                            v-text="invoice.total"
                        ></span>
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
    </AppLayout>
</template>
