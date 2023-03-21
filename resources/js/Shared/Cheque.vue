<script setup>
    import { computed } from "vue";

    const props = defineProps({
        cheque: {
            type: Object,
            required: true,
        },
    });

    let isBeyondDue = computed(() => {
        return new Date(props.cheque.due) <= new Date();
    });
</script>
<template>
    <div
        class="border p-3 bg-white rounded border-l-2 mb-2"
        :class="[cheque.is_credit ? 'border-l-green-500' : 'border-l-red-500']"
    >
        <div class="flex justify-between items-center">
            <div>
                <strong>Payee:</strong
                ><i
                    class="mx-1"
                    v-text="cheque.payee.name"
                ></i>
            </div>
            <div>
                <span
                    class="border rounded py-1 px-2  font-light"
                    :class="[isBeyondDue ? 'text-red-500 border-red-400' : 'text-gray-900 border-gray-100']"
                    v-text="cheque.due_for_humans"
                ></span>
            </div>
        </div>
        <div>
            <strong>Amount: </strong>
            <span v-text="cheque.amount_formated"></span>
        </div>
    </div>
</template>
