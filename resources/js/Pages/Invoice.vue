<script setup>
    import Card from "@/Pages/Purchases/Card.vue";
    import { Head, useForm } from "@inertiajs/vue3";
    import { computed, ref } from "vue";

    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import InputError from "@/Components/InputError.vue";
    import DialogModal from "@/Components/DialogModal.vue";


    const props = defineProps({
        invoice: Object,
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

    let hasNoInvoices = computed(() => {
        return !props.invoices.data.length;
    });

    let confirmMoving = () => {
        form.put(route("stock.add", form.storage), {
            onFinish: () => closeModal()
        }).then();
    };

    let deductingFromStorage = ref(false);

    let deductFromStorage = (invoice) => {
        deductingFromStorage.value = true;
        form.invoice = invoice.id;
    };

    let closeModal = () => {
        movingToStorage.value = null;
        deductingFromStorage.value = null;
    };

    let confirmDeduct = () => {
        form.put(route("stock.deduct", form.storage), {
            onSuccess: () => closeModal()
        }).then();
    };

</script>

<template>
    <Head :title="__('Invoice')" />
    <div class="m-2">
        <card v-if="invoice.invocable.type_string == 'Supplier'"
              :invoice="invoice"
              @moveToStorage="moveToStorage"
              :actionTitle="__('Deliver to Storage')" :printable="false"></card>
        <card v-else
              :invoice="invoice"
              @moveToStorage="deductFromStorage"
              :actionTitle="__('Deduct From Storage')" :printable="false"></card>
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
</template>

<style scoped>

</style>
