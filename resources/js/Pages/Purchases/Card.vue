<script setup>
    import Transactions from "@/Shared/Transactions.vue";

    defineProps({
        invoice: Object,
        actionTitle: String,
        printable: {
            type: Boolean,
            default: true
        }
    });

    const emit = defineEmits(["moveToStorage", "printInvoice"]);

    let moveToStorage = (moveToStorage) => {
        emit("moveToStorage", moveToStorage);
    };
</script>
<template>
    <div class="bg-white border-2 border-dashed rounded-lg ">
        <div class="p-6 md:flex rtl:flex-row-reverse md:items-center md:justify-between">
            <div class="flex rtl:flex-row-reverse items-center gap-x-4">

                <h2 v-text="invoice.total + ' SDG'" class="text-base font-semibold text-gray-800 sm:text-lg"></h2>

                <label for="totalCost" class="text-sm font-medium text-gray-600">
                    {{ __("Total Cost") }}
                </label>
            </div>

            <div class="flex-col gap-4 mt-4 sm:flex-row sm:items-center md:mt-0">
                <h2 v-text="invoice.invocable.name" class="text-gray-900 text-xl mb-3"></h2>
                <div class="flex flex rtl:flex-row-reverse space-x-4">
                    <a
                        v-if="printable"
                        :href="route('invoices.print', invoice)"
                        target="_blank"
                        class="flex items-center text-gray-600 transition-colors duration-200 gap-x-2 hover:text-emerald-500"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                        </svg>

                        <span>{{ __("Print") }}</span>
                    </a>

                    <button
                        v-if="!invoice.locked"
                        @click="moveToStorage(invoice)"
                        class="flex items-center text-gray-600 transition-colors duration-200 gap-x-2 hover:text-emerald-500"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>

                        <span v-text="actionTitle"></span>
                    </button>
                </div>
            </div>
        </div>

        <Transactions
            :invoice="invoice"
            class="w-full"
        />
    </div>
</template>
