<script setup>
    import { ref } from "vue";
    import { useForm } from "@inertiajs/vue3";
    import DangerButton from "@/Components/DangerButton.vue";
    import DialogModal from "@/Components/DialogModal.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";

    const confirmingProductDeletion = ref(false);

    const form = useForm({});

    const props = defineProps({
        product: Object,
    });

    const deleteProduct = () => {
        form.delete(route("products.destroy", props.product.id), {
            preserveScroll: true,
        });
    };

    const confirmProductDeletion = () => {
        confirmingProductDeletion.value = true;
    };

    const closeModal = () => {
        confirmingProductDeletion.value = false;
    };
</script>

<template>
    <button
        class="inline-flex items-center text-gray-600 gap-x-1 hover:text-red-500 focus:outline-none"
        @click="confirmProductDeletion"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-4 h-4"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
            />
        </svg>

        <span>{{ __("Delete") }}</span>
    </button>

    <!-- Delete Account Confirmation Modal -->
    <DialogModal
        :show="confirmingProductDeletion"
        @close="closeModal"
    >
        <template #title>
            <h2 class="font-semibold text-gray-800">
                {{ __("Delete Product") }}
            </h2>
        </template>

        <template #content>
            <p
                class="text-gray-500 rtl:text-right"
            >
                {{
                    __(
                        "Are you sure you want to delete this Product? Once Product is deleted, all of its resources and data will be permanently deleted"
                    )
                }}.
            </p>
        </template>

        <template #footer>
            <SecondaryButton @click="closeModal">
                {{ __("Cancel") }}
            </SecondaryButton>

            <DangerButton
                class="ml-3 rtl:mr-3 rtl:ml-0"
                @click="deleteProduct"
            >
                {{ __("Delete Product") }}
            </DangerButton>
        </template>
    </DialogModal>
</template>
