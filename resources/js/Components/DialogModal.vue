<script setup>
    import Modal from "./Modal.vue";

    const emit = defineEmits(["close"]);

    defineProps({
        show: {
            type: Boolean,
            default: false,
        },
        maxWidth: {
            type: String,
            default: "2xl",
        },
        closeable: {
            type: Boolean,
            default: true,
        },
    });

    const close = () => {
        emit("close");
    };
</script>

<template>
    <Modal
        :show="show"
        :max-width="maxWidth"
        :closeable="closeable"
        @close="close"
    >
        <div
            class="px-6 py-4"
            :dir="preferences('language') == 'ar' ? 'rtl' : 'ltr'"
        >
            <div class="text-lg">
                <slot name="title" />
            </div>

            <div class="mt-4 rtl:text-right">
                <slot name="content" />
            </div>
        </div>

        <div
            class="flex rtl:flex-row-reverse flex-row ltr:justify-end px-6 py-4 bg-gray-100 text-right rtl:justify-start"
            :dir="preferences('language') == 'ar' ? 'rtl' : 'ltr'"
        >
            <slot name="footer" />
        </div>
    </Modal>
</template>
